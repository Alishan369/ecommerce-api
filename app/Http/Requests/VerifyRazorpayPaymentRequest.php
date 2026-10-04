<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRazorpayPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // order ownership is checked in the controller (findForUser)
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:40'],
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_payment_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ];
    }
}
