<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->withoutTrashed()],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'slug.alpha_dash' => 'Slug may only contain letters, numbers, dashes and underscores.',
            'slug.unique' => 'Category slug must be unique.',
            'parent_id.exists' => 'Selected parent category does not exist.',
        ];
    }
}
