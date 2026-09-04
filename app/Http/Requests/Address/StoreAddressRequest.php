<?php

namespace App\Http\Requests\Address;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_name' => 'required|string|max:100',
            'recipient_phone' => 'required|string|max:15',
            'address_line' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_name.required' => 'Naam zaruri hai.',
            'recipient_phone.required' => 'Phone number zaruri hai.',
            'address_line.required' => 'Address zaruri hai.',
            'city.required' => 'City zaruri hai.',
            'state.required' => 'State zaruri hai.',
            'pincode.required' => 'Pincode zaruri hai.',
            'pincode.size' => 'Pincode 6 digits ka hona chahiye.',
        ];
    }
}
