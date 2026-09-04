<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
            if ($category->isDirty('name')) {
                $category->slug = static::generateUniqueSlug($category->name, $category->id);
            }
        });

        // Prevent orphaned files piling up in storage when a category is removed.
        // static::deleting(function (Category $category) {
        //     if ($category->image) {
        //         Storage::disk('public')->delete($category->image);
        //     }
        // });
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreCategoryId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 1;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreCategoryId, fn ($query) => $query->where('id', '!=', $ignoreCategoryId))
            ->exists()) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Exposed as `image_url` in API responses via CategoryResource.
     */
    // public function getImageUrlAttribute(): ?string
    // {
    //     return $this->image ? Storage::disk('public')->url($this->image) : null;
    // }

    public static function findBySlug(string $slug): self
    {
        return static::where('slug', $slug)->firstOrFail();
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
