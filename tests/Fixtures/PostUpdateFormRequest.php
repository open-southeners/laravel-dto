<?php

namespace OpenSoutheners\LaravelDto\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostUpdateFormRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'string',
            'content' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'publish_at' => 'datetime',
        ];
    }
}
