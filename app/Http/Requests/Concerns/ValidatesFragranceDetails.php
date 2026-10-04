<?php

namespace App\Http\Requests\Concerns;

use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * Perfume-specific product fields shared by the create and update requests.
 * The admin form posts multipart data, so `notes` arrives as a JSON string.
 */
trait ValidatesFragranceDetails
{
    protected function prepareFragranceDetails(): void
    {
        $merge = [];

        if ($this->has('notes') && is_string($this->input('notes'))) {
            $decoded = json_decode($this->input('notes'), true);
            $merge['notes'] = is_array($decoded) ? $decoded : $this->input('notes');
        }

        foreach (['gender', 'fragrance_family'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = strtolower(trim((string) $this->input($field)));
            }
        }

        if ($merge) {
            $this->merge($merge);
        }
    }

    protected function fragranceRules(): array
    {
        $note = ['string', 'max:40'];

        return [
            'short_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::in(Product::GENDERS)],
            'concentration' => ['sometimes', 'nullable', 'string', 'max:20'],
            'size_ml' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5000'],
            'fragrance_family' => ['sometimes', 'nullable', Rule::in(Product::FAMILIES)],
            'notes' => ['sometimes', 'nullable', 'array:top,heart,base'],
            'notes.top' => ['sometimes', 'array', 'max:8'],
            'notes.heart' => ['sometimes', 'array', 'max:8'],
            'notes.base' => ['sometimes', 'array', 'max:8'],
            'notes.top.*' => $note,
            'notes.heart.*' => $note,
            'notes.base.*' => $note,
        ];
    }

    /** Drops blank notes so the pyramid never renders empty tiers. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key === null && is_array($data['notes'] ?? null)) {
            $notes = collect(['top', 'heart', 'base'])
                ->mapWithKeys(fn (string $tier) => [$tier => array_values(array_filter(
                    array_map('trim', $data['notes'][$tier] ?? []),
                ))])
                ->filter()
                ->all();

            $data['notes'] = $notes ?: null;
        }

        return $data;
    }
}
