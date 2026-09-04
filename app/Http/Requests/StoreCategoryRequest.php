<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    // public function authorize(): bool
    // {
    //     return true;
    // }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')],
            'description' => ['sometimes', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'slug.alpha_dash' => 'Slug may only contain letters, numbers, dashes and underscores.',
            'slug.unique' => 'Category slug must be unique.',
            'image.image' => 'The file must be a valid image.',
            'image.max' => 'The image may not be larger than 2MB.',
        ];
    }
}
