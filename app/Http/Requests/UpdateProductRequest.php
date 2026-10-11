<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesFragranceDetails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use ValidatesFragranceDetails;

    public function rules(): array
    {
        return [
            ...$this->fragranceRules(),
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')->withoutTrashed()],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            // The admin route binds {product:id}, so this is the model.
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'sku' => ['prohibited'], // generated once on creation, never edited
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'lt:price'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_bestseller' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }
}
