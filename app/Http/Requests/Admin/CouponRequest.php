<?php

namespace App\Http\Requests\Admin;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST) and update (PUT/PATCH) a coupon. Route is admin-gated. */
class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => Coupon::normalize($this->input('code'))]);
        }
    }

    public function rules(): array
    {
        $coupon = $this->route('coupon');
        $updating = $coupon instanceof Coupon;
        $required = $updating ? 'sometimes' : 'required';
        $type = $this->input('type', $coupon?->type);

        return [
            'code' => [$required, 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => [$required, Rule::in(Coupon::TYPES)],
            'value' => $type === Coupon::TYPE_FREE_SHIPPING
                ? ['sometimes', 'nullable', 'numeric', 'min:0']
                : [$required, 'numeric', 'gt:0', ...($type === Coupon::TYPE_PERCENT ? ['max:100'] : ['max:100000'])],
            'max_discount' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'min_order_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', ...($this->filled('starts_at') ? ['after:starts_at'] : [])],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_user_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'first_order_only' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dashes or underscores only (no spaces).',
            'code.unique' => 'A coupon with this code already exists.',
            'value.max' => 'A percentage discount can\'t be more than 100%.',
            'value.gt' => 'Enter a discount greater than zero.',
            'expires_at.after' => 'The expiry must be after the start date.',
        ];
    }

    /** Free-shipping coupons carry no value / cap; normalise empty strings from the form. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key === null) {
            if (($data['type'] ?? $this->route('coupon')?->type) === Coupon::TYPE_FREE_SHIPPING) {
                $data['value'] = 0;
                $data['max_discount'] = null;
            }
            if (array_key_exists('min_order_amount', $data)) {
                $data['min_order_amount'] ??= 0;
            }
        }

        return $data;
    }
}
