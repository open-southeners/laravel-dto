<?php

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TagUpdateFormRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => ['required', 'string'],
            'taggable' => ['required', 'string'],
            'taggable_type' => ['required', 'string'],
        ];
    }
}
