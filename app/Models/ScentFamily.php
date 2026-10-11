<?php

namespace App\Models;

use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A scent family's storefront presentation — name, colours, photo, order and
 * whether it shows in "Shop by scent". `key` is fixed and matches
 * products.fragrance_family (see Product::FAMILIES).
 */
class ScentFamily extends Model
{
    protected $fillable = ['label', 'color_from', 'color_to', 'color_bg', 'image', 'sort_order', 'is_featured'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Adds `products_count`: active, not-deleted products in the family. */
    public function scopeWithProductCount(Builder $query): Builder
    {
        return $query->addSelect([
            'products_count' => Product::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('products.fragrance_family', 'scent_families.key')
                ->where('products.is_active', true),
        ]);
    }

    public function getImageUrlAttribute(): ?string
    {
        return StoredImage::url($this->image);
    }
}
