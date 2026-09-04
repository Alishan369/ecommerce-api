<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product is required.',
            'product_id.exists' => 'This product does not exist.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Maximum 20 quantity allowed per item.',
        ];
    }
}
