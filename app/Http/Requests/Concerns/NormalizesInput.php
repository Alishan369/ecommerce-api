<?php

namespace App\Http\Requests\Concerns;

/**
 * Normalises raw input before validation. Only strings are touched: an array or
 * number is left as it is, so the rules reject it with a 422 instead of a 500.
 */
trait NormalizesInput
{
    /** @param  array<string, callable(string): mixed>  $normalizers  field => normaliser */
    protected function normalize(array $normalizers): void
    {
        foreach ($normalizers as $field => $normalize) {
            if (is_string($value = $this->input($field))) {
                $this->merge([$field => $normalize($value)]);
            }
        }
    }
}
