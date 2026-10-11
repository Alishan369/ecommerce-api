<?php

namespace App\Http\Requests\Address;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Support\India;
use Illuminate\Foundation\Http\FormRequest;

/** Routes are behind auth:sanctum; AddressRepository scopes every address to its owner. */
class StoreAddressRequest extends FormRequest
{
    use NormalizesInput;

    protected function prepareForValidation(): void
    {
        $this->normalize(['phone' => India::phone(...), 'pincode' => India::pincode(...)]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:'.India::PHONE_PATTERN],
            'address_line' => ['required', 'string', 'min:5', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'regex:'.India::PINCODE_PATTERN],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => India::PHONE_MESSAGE,
            'pincode.regex' => India::PINCODE_MESSAGE,
        ];
    }
}
