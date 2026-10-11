<?php

namespace App\Http\Requests;

use App\Repositories\CartRepository;
use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function rules(): array
    {
        // Whether the product exists and is on sale is checked by CartRepository (one query, clearer message).
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartRepository::MAX_QUANTITY_PER_ITEM],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product is required.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'You can add up to '.CartRepository::MAX_QUANTITY_PER_ITEM.' of this item.',
        ];
    }
}
