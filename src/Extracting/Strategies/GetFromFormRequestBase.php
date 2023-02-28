<?php

namespace Knuckles\Scribe\Extracting\Strategies;

use Illuminate\Routing\Route;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Dingo\Api\Http\FormRequest as DingoFormRequest;
use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest;
use Knuckles\Scribe\Extracting\FindsFormRequestForMethod;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Tools\ConsoleOutputUtils as c;
use Knuckles\Scribe\Tools\Globals;
use ReflectionClass;
use ReflectionFunctionAbstract;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

class GetFromFormRequestBase extends Strategy
{
    use ParsesValidationRules, FindsFormRequestForMethod;

    protected string $customParameterDataMethodName = '';

    public function __invoke(ExtractedEndpointData $endpointData, array $routeRules = []): ?array
    {
        $this->endpointData = $endpointData;
        return $this->getParametersFromFormRequest($endpointData->method, $endpointData->route);
    }

    public function getParametersFromFormRequest(ReflectionFunctionAbstract $method, Route $route): array
    {
        if (!$formRequestReflectionClass = $this->getFormRequestReflectionClass($method)) {
            return [];
        }

        if (!$this->isFormRequestMeantForThisStrategy($formRequestReflectionClass)) {
            return [];
        }

        $className = $formRequestReflectionClass->getName();

        // Get the params name/example value
        $params = [];
        foreach($this->endpointData->urlParameters as $param) {
            $params[$param->name] = $param->example;
        }

        $request = \Illuminate\Http\Request::create($route->uri(), $route->methods()[0], $params);

        if (Globals::$__instantiateFormRequestUsing) {
            $formRequest = call_user_func_array(Globals::$__instantiateFormRequestUsing, [$className, $route, $method]);
        } else {
            /**
             * instanciate a new form request
             */
            $formRequest = \Illuminate\Foundation\Http\FormRequest::createFrom($request, new $className);
        }

        $route->bind($formRequest);

        app('router')->substituteBindings($route);
        app('router')->substituteImplicitBindings($route);

        $formRequest->server->set('REQUEST_METHOD', $route->methods()[0]);

        $parametersFromFormRequest = $this->getParametersFromValidationRules(
            $this->getRouteValidationRules($formRequest),
            $this->getCustomParameterData($formRequest)
        );

        return $this->normaliseArrayAndObjectParameters($parametersFromFormRequest);
    }

    /**
     * @param LaravelFormRequest|DingoFormRequest $formRequest
     *
     * @return mixed
     */
    protected function getRouteValidationRules($formRequest)
    {
        if (method_exists($formRequest, 'validator')) {
            $validationFactory = app(ValidationFactory::class);

            return call_user_func_array([$formRequest, 'validator'], [$validationFactory])
                ->getRules();
        } else {
            return call_user_func_array([$formRequest, 'rules'], []);
        }
    }

    /**
     * @param LaravelFormRequest|DingoFormRequest $formRequest
     */
    protected function getCustomParameterData($formRequest)
    {
        if (method_exists($formRequest, $this->customParameterDataMethodName)) {
            return call_user_func_array([$formRequest, $this->customParameterDataMethodName], []);
        }

        c::warn("No {$this->customParameterDataMethodName}() method found in " . get_class($formRequest) . ". Scribe will only be able to extract basic information from the rules() method.");

        return [];
    }

    protected function getMissingCustomDataMessage($parameterName)
    {
        return "No data found for parameter '$parameterName' in your {$this->customParameterDataMethodName}() method. Add an entry for '$parameterName' so you can add a description and example.";
    }

    protected function isFormRequestMeantForThisStrategy(ReflectionClass $formRequestReflectionClass): bool
    {
        return $formRequestReflectionClass->hasMethod($this->customParameterDataMethodName);
    }

}

