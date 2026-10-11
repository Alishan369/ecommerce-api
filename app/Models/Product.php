<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUniqueSlug, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'description', 'short_description', 'image',
        'gender', 'concentration', 'size_ml', 'fragrance_family', 'notes',
        'price', 'sale_price', 'stock', 'is_active', 'is_featured', 'is_bestseller',
    ];

    public const GENDERS = ['men', 'women', 'unisex'];

    /** Scent families — also drive the storefront's generated bottle artwork colours. */
    public const FAMILIES = [
        'woody', 'fresh', 'aquatic', 'citrus', 'floral', 'fruity', 'musky',
        'gourmand', 'oriental', 'spicy', 'leather', 'oud', 'green',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_bestseller' => 'boolean',
        'stock' => 'integer',
        'size_ml' => 'integer',
        'notes' => 'array',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product) {
            if (empty($product->sku)) {
                $product->sku = static::generateSku($product->fragrance_family);
            }
            if (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });
    }

    /** SJ-<FAMILY>-<next number>, e.g. SJ-WOO-0042 — unique, human-readable, never typed in. */
    public static function generateSku(?string $family = null): string
    {
        $code = $family ? strtoupper(substr($family, 0, 3)) : 'GEN';
        $next = ((int) static::withTrashed()->max('id')) + 1;

        do {
            $sku = sprintf('SJ-%s-%04d', $code, $next++);
        } while (static::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return StoredImage::url($this->image);
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if (! $this->sale_price || $this->sale_price >= $this->price) {
            return null;
        }

        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }
}
