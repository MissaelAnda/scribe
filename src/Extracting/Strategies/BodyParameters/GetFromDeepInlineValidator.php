<?php

namespace Knuckles\Scribe\Extracting\Strategies\BodyParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\MethodAstParser;
use PhpParser\Node;
use Illuminate\Support\Str;

class GetFromDeepInlineValidator extends GetFromInlineValidator
{
    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        if (!$endpointData->method instanceof \ReflectionMethod) {
            return [];
        }

        [$validationRules, $customParameterData] = $this->lookForInlineValidationRulesRecursively($endpointData);

        $bodyParametersFromValidationRules = $this->getParametersFromValidationRules($validationRules, $customParameterData);
        return $this->normaliseArrayAndObjectParameters($bodyParametersFromValidationRules);
    }

    public function lookForInlineValidationRulesRecursively(ExtractedEndpointData $endpointData): array
    {
        $methodReflection = $endpointData->method;

        $depth = $this->config->get('scribe.deep_search.max_depth', 3);
        $allowedNamespaces = $this->config->get('scribe.deep_search.namespaces', ['App']);
        $allowedNamespaces = collect($allowedNamespaces);

        do {
            $methodAst = MethodAstParser::getMethodAst($methodReflection);
            [$validationRules, $customParameterData] = $this->lookForInlineValidationRules($methodAst);

            $depth--;
            if ($validationRules || $depth <= 0) break;
            $methodReflection = null;

            foreach ($methodAst->stmts as $statement) {
                if ($statement instanceof Node\Stmt\Return_) {
                    $statement = $statement->expr;
                }

                if (
                    $statement instanceof Node\Stmt\Expression &&
                    $statement->expr instanceof Node\Expr\Assign
                ) {
                    $statement = $statement->expr->expr;
                }

                if ($statement instanceof Node\Expr\StaticCall) {
                    $class = null;
                    if (in_array('parent', $statement->class->parts)) {
                        $class = $endpointData->controller->getParentClass();
                    } elseif (in_array('static', $statement->class->parts)) {
                        $class = $endpointData->controller;
                    }

                    try {
                        $methodReflection = $class?->getMethod($statement->name->name);
                    } catch (\ReflectionException $_) {}
                } else if (
                    $statement instanceof Node\Expr\MethodCall &&
                    $statement->var instanceof Node\Expr\Variable &&
                    $statement->var->name === 'this'
                ) {
                    try {
                        $methodReflection = $endpointData->controller->getMethod($statement->name->name);
                    } catch (\ReflectionException $_) {}
                }

                // If the method is from a non-allowed namespace we ignore it and keep searching
                if ($methodReflection) {
                    $namespace = explode('\\', $methodReflection->class)[0] ?? null;
                    if ($namespace !== null && !$allowedNamespaces->contains(
                        fn ($n) => Str::startsWith($n, $namespace)
                    )) {
                        $methodReflection = null;
                    } else {
                        break;
                    }
                }
            }
        } while ($methodReflection);

        return [$validationRules, $customParameterData];
    }
}
