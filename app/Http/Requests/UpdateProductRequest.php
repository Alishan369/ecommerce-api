<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeProduct = $this->route('product');
        $productId = $routeProduct instanceof Product ? $routeProduct->id : $routeProduct;

        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($productId)],
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'lt:price'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }
}
