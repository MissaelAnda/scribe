<?php

namespace Knuckles\Scribe\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

use function PHPUnit\Framework\assertNotNull;

class TestRequestRouteParam extends FormRequest
{
    public function rules()
    {
        $user = $this->user;
        assertNotNull($user);

        $user = $this->route('user');
        assertNotNull($user);

        $user = $this->attributes()['user'];
        assertNotNull($user);

        return [
            'new_user_id' => 'required|integer|not_in:' . $user->id,
        ];
    }

    public function bodyParameters()
    {
        return [
            'new_user_id' => [
                'description' => 'required but not the same id as the url\'s user param',
            ],
        ];
    }
}
