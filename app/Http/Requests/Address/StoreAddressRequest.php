<?php

namespace App\Http\Requests\Address;

use App\Support\IndianPhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:sanctum; ownership is enforced in the repository
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('phone')) {
            $merge['phone'] = IndianPhone::normalize($this->input('phone'));
        }
        if ($this->has('pincode')) {
            $merge['pincode'] = preg_replace('/\s+/', '', (string) $this->input('pincode'));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:'.IndianPhone::PATTERN],
            'address_line' => ['required', 'string', 'min:5', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'regex:/^[1-9][0-9]{5}$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
            'pincode.regex' => 'Enter a valid 6-digit PIN code.',
        ];
    }
}
