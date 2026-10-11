<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Services\Payments\PaymentService;
use App\Support\India;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutOrderRequest extends FormRequest
{
    use NormalizesInput;

    protected function prepareForValidation(): void
    {
        $this->normalize(['shipping_phone' => India::phone(...), 'shipping_pincode' => India::pincode(...)]);
    }

    public function rules(): array
    {
        // The order email is always the signed-in account's email (set in OrderRepository).
        return [
            'shipping_name' => ['required', 'string', 'max:100'],
            'shipping_phone' => ['required', 'string', 'regex:'.India::PHONE_PATTERN],
            'shipping_address_line' => ['required', 'string', 'min:5', 'max:1000'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_state' => ['required', 'string', 'max:100'],
            'shipping_pincode' => ['required', 'string', 'regex:'.India::PINCODE_PATTERN],
            // Online payment is only accepted while the gateway is configured.
            'payment_method' => ['required', Rule::in(app(PaymentService::class)->methodCodes())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'save_address' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_phone.regex' => India::PHONE_MESSAGE,
            'shipping_pincode.regex' => India::PINCODE_MESSAGE,
            'payment_method.in' => 'This payment method isn\'t available right now.',
        ];
    }
}
