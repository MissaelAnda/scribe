<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TestSubController extends TestController
{
    use AuthorizesRequests;

    public function deepInlineValidate(Request $request)
    {
        return parent::deepInlineValidate($request);
    }

    public function deepThisInlineValidate(Request $request)
    {
        $this->authorize('test');

        return $this->deepInlineValidate($request);
    }

    public function tooDeepInlineValidation(Request $request)
    {
        return $this->tooDeepInlineValidation($request);
    }

    public function deepStaticCallInlineValidation(Request $request)
    {
        return static::deepStaticValidation($request);
    }

    public static function deepStaticValidation(Request $request)
    {
        $request->validate([
            // The id of the user. Example: 9
            'user_id' => 'int|required',
            // The id of the room.
            'room_id' => ['string', 'in:3,5,6'],
            // Whether to ban the user forever. Example: false
            'forever' => 'boolean',
            // Just need something here
            'another_one' => 'numeric',
            'even_more_param' => 'array',
            'book.name' => 'string',
            'book.author_id' => 'integer',
            'book.pages_count' => 'integer',
            'ids.*' => 'integer',
            // The first name of the user. Example: John
            'users.*.first_name' => ['string'],
            // The last name of the user. Example: Doe
            'users.*.last_name' => 'string',
        ]);
    }
}
