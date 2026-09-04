<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:categories,name,'.$this->route('slug').',slug',
            'description' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.unique' => 'Category name must be unique.',
            'is_active.required' => 'Category status is required.',
            'is_active.boolean' => 'Category status must be true or false.',
            'slug.alpha_dash' => 'Slug may only contain letters, numbers, dashes and underscores.',
            'slug.unique' => 'Category slug must be unique.',
        ];
    }
}
