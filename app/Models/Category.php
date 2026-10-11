<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasUniqueSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'sort_order',
        'parent_id',
    ];

    /** Same as the column defaults, so a category is complete straight after create(). */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = static::generateUniqueSlug($category->name);
            }
        });

        static::updating(function (Category $category) {
            // Slug is intentionally NOT auto-regenerated on name change —
            // that would silently break every existing link to this category.
            // Admins change the slug explicitly if they want a new URL.
            if (empty($category->slug)) {
                $category->slug = static::generateUniqueSlug($category->name, $category->id);
            }
        });

        // Only clean up the file on permanent deletion — a soft-deleted
        // category can still be restored, and shouldn't lose its image.
        static::forceDeleted(fn (Category $category) => StoredImage::delete($category->image));
    }

    public function getImageUrlAttribute(): ?string
    {
        return StoredImage::url($this->image);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
