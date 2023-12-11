<?php

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostCreateFormRequest extends FormRequest
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
            'author_id' => ['nullable', 'int'],
            'category_id' => ['nullable', Rule::exists('categories'.'id')],
            'tags' => ['nullable', 'array'],
            'publish_at' => 'datetime',
        ];
    }
}
