<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductFiltersRequest extends FormRequest
{
    public const SORTS = ['featured', 'latest', 'newest', 'price_asc', 'price_desc', 'name_asc', 'discount', 'stock_asc'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'nullable', Rule::in(self::SORTS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'min_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'in_stock' => ['sometimes', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'bestseller' => ['sometimes', 'boolean'],
            'on_sale' => ['sometimes', 'boolean'],
            'exclude' => ['sometimes', 'integer'],
            'gender' => ['sometimes', 'nullable', Rule::in(Product::GENDERS)],
            'family' => ['sometimes', 'nullable', Rule::in(Product::FAMILIES)],
            // Admin listing only.
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
