<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class LookupOrderRequest extends FormRequest
{
    use NormalizesInput;

    protected function prepareForValidation(): void
    {
        $this->normalize(['order_number' => 'strtoupper', 'email' => 'strtolower']);
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'regex:/^'.Order::NUMBER_PATTERN.'$/'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['order_number.regex' => 'Enter the order number from your confirmation, e.g. ORD-20261005-AB12CD34.'];
    }
}
