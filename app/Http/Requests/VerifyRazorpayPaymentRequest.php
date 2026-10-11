<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/** Order ownership is checked in PaymentController (findForUser), the signature in PaymentService. */
class VerifyRazorpayPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'regex:/^'.Order::NUMBER_PATTERN.'$/'],
            'razorpay_order_id' => ['required', 'string', 'alpha_dash', 'max:64'],
            'razorpay_payment_id' => ['required', 'string', 'alpha_dash', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'alpha_num', 'max:128'],
        ];
    }
}
