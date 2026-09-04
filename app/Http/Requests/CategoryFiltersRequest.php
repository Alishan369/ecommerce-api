<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryFiltersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['boolean', 'sometimes'],
        ];
    }
}
