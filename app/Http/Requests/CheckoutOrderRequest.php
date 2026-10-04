<?php

namespace App\Http\Requests;

use App\Services\Payments\PaymentService;
use App\Support\IndianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipping_phone' => IndianPhone::normalize($this->input('shipping_phone')),
            'shipping_pincode' => preg_replace('/\s+/', '', (string) $this->input('shipping_pincode')),
        ]);
    }

    public function rules(): array
    {
        // The order email is always the signed-in account's email (set in OrderRepository).
        return [
            'shipping_name' => ['required', 'string', 'max:100'],
            'shipping_phone' => ['required', 'string', 'regex:'.IndianPhone::PATTERN],
            'shipping_address_line' => ['required', 'string', 'min:5', 'max:1000'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_state' => ['required', 'string', 'max:100'],
            'shipping_pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            // Online payment is only accepted while the gateway is configured.
            'payment_method' => ['required', Rule::in(app(PaymentService::class)->methodCodes())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'save_address' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
            'shipping_pincode.regex' => 'Enter a valid 6-digit PIN code.',
            'payment_method.in' => 'This payment method isn\'t available right now.',
        ];
    }
}
