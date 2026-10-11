<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scent families become admin-managed: name, colours, photo, order and whether
 * they appear in the home page's "Shop by scent". The keys stay fixed (products
 * store them in `fragrance_family`, see Product::FAMILIES).
 *
 * Starts with the storefront's previous built-in names/colours, and moves any
 * family photos from Site media (`family.*` slots) onto the families.
 */
return new class extends Migration
{
    /** key => [label, from, to, bg] — the previous built-in palette, in "Shop by scent" order. */
    private const DEFAULTS = [
        'woody' => ['Woody', '#a2714b', '#3e2a1f', '#f3e7da'],
        'fresh' => ['Fresh', '#a6e0d2', '#2f8379', '#e6f4f0'],
        'aquatic' => ['Aquatic', '#9ccbff', '#1f5fa8', '#e5f0fb'],
        'floral' => ['Floral', '#f9bfd0', '#c2547a', '#fbe8ee'],
        'fruity' => ['Fruity', '#ffbd94', '#df4f5d', '#fdeae4'],
        'oud' => ['Oud', '#7e5a8f', '#241628', '#ece4ef'],
        'gourmand' => ['Gourmand', '#f5d6a8', '#a3623a', '#f8ecdc'],
        'citrus' => ['Citrus', '#ffe28f', '#ee9a2e', '#fdf4dc'],
        'musky' => ['Musky', '#ecdff5', '#9579ad', '#f3edf7'],
        'spicy' => ['Spicy', '#f4a477', '#992f1f', '#f9e3d8'],
        'green' => ['Green', '#bfe095', '#4d7a3a', '#ecf4e2'],
        'leather' => ['Leather', '#bb875e', '#4a2b1d', '#f1e4d8'],
        'oriental' => ['Amber', '#f3bf6b', '#8e3b2e', '#f9e9d3'],
    ];

    public function up(): void
    {
        Schema::create('scent_families', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();         // matches products.fragrance_family
            $table->string('label', 40);
            $table->string('color_from', 7);              // #rrggbb — gradient start (chips, rings, bottle art)
            $table->string('color_to', 7);                // #rrggbb — gradient end
            $table->string('color_bg', 7);                // #rrggbb — soft background tint
            $table->string('image')->nullable();          // photo for circles, cards and banners
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(true); // shown in the home page's "Shop by scent"
            $table->timestamps();
        });

        $now = now();
        $order = 0;
        foreach (self::DEFAULTS as $key => [$label, $from, $to, $bg]) {
            DB::table('scent_families')->insert([
                'key' => $key, 'label' => $label, 'color_from' => $from, 'color_to' => $to, 'color_bg' => $bg,
                'sort_order' => $order++, 'is_featured' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Family photos used to live in Site media slots "family.<key>" — move them over.
        if (Schema::hasTable('site_media')) {
            foreach (DB::table('site_media')->where('slot', 'like', 'family.%')->get() as $media) {
                $key = substr($media->slot, strlen('family.'));
                if ($media->image) {
                    DB::table('scent_families')->where('key', $key)->update(['image' => $media->image]);
                }
                DB::table('site_media')->where('id', $media->id)->delete(); // the file now belongs to the family
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_media')) {
            foreach (DB::table('scent_families')->whereNotNull('image')->get() as $family) {
                DB::table('site_media')->updateOrInsert(
                    ['slot' => "family.{$family->key}"],
                    ['image' => $family->image, 'video' => null, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        Schema::dropIfExists('scent_families');
    }
};
