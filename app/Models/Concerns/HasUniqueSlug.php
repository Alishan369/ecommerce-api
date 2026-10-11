<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/** "rose-oud", then "rose-oud-1", "rose-oud-2"… unique across soft-deleted rows too. */
trait HasUniqueSlug
{
    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = $base = Str::slug($name);
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
