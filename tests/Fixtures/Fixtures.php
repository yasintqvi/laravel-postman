<?php

namespace YasinTgh\LaravelPostman\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

enum Status: string
{
    case Active = 'active';
    case Off = 'off';
}

class Clock
{
    public function year(): int
    {
        return 2026;
    }
}

/** Uses route(), user() and an injected service, which used to empty the body. */
class StorePostRequest extends FormRequest
{
    public function rules(Clock $clock): array
    {
        return [
            'title' => 'required|string',
            'slug' => 'unique:posts,slug,' . $this->route('post'),
            'year' => 'integer|max:' . $clock->year(),
            'owner' => $this->user()?->id ? 'string' : 'nullable|string',
        ];
    }
}

class EmptyRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }
}

class OtherType
{
}

class PostController
{
    public function index()
    {
    }

    public function update(StorePostRequest|OtherType $request)
    {
    }

    public function store(EmptyRequest $request)
    {
    }
}

class UserController
{
    public function index()
    {
    }
}
