<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\ScentFamily;
use App\Models\SiteMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Starter photos and videos for the storefront, loaded as ordinary admin-managed
 * records: home banners, site media slots and category photos. Afterwards the
 * shop shows only what is in the database — admins replace or delete any of it.
 *
 * Safe to re-run (`php artisan db:seed --class=StorefrontMediaSeeder`): it only
 * fills gaps — a placement with no banners, an empty slot, a category without a
 * photo (or whose photo file is missing). It never overwrites admin uploads.
 *
 * Files: database/seeders/media (CC0 / public domain stock, see credits.json,
 * plus the store's own photo and videos).
 */
class StorefrontMediaSeeder extends Seeder
{
    private const SOURCE = __DIR__.'/media';

    /** Category name/slug → photo. Specific scent words win over collection words. */
    private const CATEGORY_PHOTOS = [
        '/gift|kit|combo|hamper|\bbox|\bset\b/i' => 'categories/gifts.webp',
        '/mist|\bdeo|spray/i' => 'categories/mists.webp',
        '/aqua|marine|ocean/i' => 'families/aquatic.webp',
        '/fresh/i' => 'families/fresh.webp',
        '/flor|rose|jasmin|mogra/i' => 'families/floral.webp',
        '/oud|agar/i' => 'families/oud.webp',
        '/amber|oriental/i' => 'families/amber.webp',
        '/wood|sandal|cedar/i' => 'families/woody.webp',
        '/citrus/i' => 'families/citrus.webp',
        '/fruit/i' => 'families/fruity.webp',
        '/musk|powder/i' => 'families/musky.webp',
        '/spic/i' => 'families/spicy.webp',
        '/leather/i' => 'families/leather.webp',
        '/green|herb/i' => 'families/green.webp',
        '/gourmand|vanilla/i' => 'families/gourmand.webp',
        '/attar|\boil/i' => 'categories/attars.webp',
        '/\bwomen\b|\bher\b|ladies/i' => 'categories/women.webp',
        '/\bmen\b|\bhim\b|gents/i' => 'categories/men.webp',
        '/unisex|everyone/i' => 'categories/unisex.webp',
    ];

    public function run(): void
    {
        $this->categories();
        $this->banners();
        $this->siteMedia();
        $this->scentFamilies();
    }

    /** A photo for every scent family that has none (or whose file is missing). */
    private function scentFamilies(): void
    {
        $disk = Storage::disk('public');

        ScentFamily::query()->each(function (ScentFamily $family) use ($disk) {
            $has = $family->image && (Str::startsWith($family->image, ['http://', 'https://']) || $disk->exists($family->image));
            $file = 'families/'.($family->key === 'oriental' ? 'amber' : $family->key).'.webp';

            if (! $has && is_file(self::SOURCE.'/'.$file)) {
                $family->forceFill(['image' => $this->copy($file, 'scent-families')])->saveQuietly();
            }
        });
    }

    private function categories(): void
    {
        $disk = Storage::disk('public');

        Category::query()->each(function (Category $category) use ($disk) {
            $has = $category->image && (Str::startsWith($category->image, ['http://', 'https://']) || $disk->exists($category->image));
            if ($has) {
                return;
            }

            $text = $category->name.' '.str_replace('-', ' ', (string) $category->slug);
            foreach (self::CATEGORY_PHOTOS as $pattern => $file) {
                if (preg_match($pattern, $text)) {
                    $category->forceFill(['image' => $this->copy($file, 'categories')])->saveQuietly();

                    return;
                }
            }
        });
    }

    private function banners(): void
    {
        $roots = Category::query()->whereNull('parent_id')->where('is_active', true)->get();
        $find = fn (string $pattern) => $roots->first(fn (Category $c) => preg_match($pattern, $c->name));
        $url = fn (?Category $c, string $fallback) => $c ? "/collections/{$c->slug}" : $fallback;

        $gifts = $find('/gift|combo|set/i');
        $men = $find('/\bmen\b|\bhim\b/i');
        $women = $find('/women|\bher\b/i');
        $attars = $find('/attar/i');

        $hero = [
            ['eyebrow' => 'Fine Fragrances', 'title' => 'Find the scent that feels like you.', 'subtitle' => 'Perfumes for men, women and everyone in between — chosen for character, priced for everyday wear.', 'cta_label' => 'Shop all perfumes', 'link_url' => '/shop', 'image' => 'banners/signature.webp', 'text_tone' => 'dark'],
            ['eyebrow' => 'Offers', 'title' => 'Treat yourself for less', 'subtitle' => 'Our biggest savings on perfumes, attars and gift sets.', 'cta_label' => 'Shop offers', 'link_url' => '/offers', 'image' => 'videos/spray-poster.webp', 'video' => 'videos/spray.mp4', 'text_tone' => 'light'],
            $gifts ? ['eyebrow' => 'The gift edit', 'title' => 'Gifts that linger long after the day', 'subtitle' => $gifts->description ?: 'Discovery kits, couple combos and festive boxes — ready to gift.', 'cta_label' => "Shop {$gifts->name}", 'link_url' => $url($gifts, '/shop'), 'image' => 'categories/gifts.webp', 'text_tone' => 'light'] : null,
            $men ? ['eyebrow' => 'For him', 'title' => 'Bold, woody and unforgettable', 'subtitle' => $men->description, 'cta_label' => "Shop {$men->name}", 'link_url' => $url($men, '/shop'), 'image' => 'categories/men.webp', 'text_tone' => 'light'] : null,
            $women ? ['eyebrow' => 'For her', 'title' => 'Soft florals, lasting warmth', 'subtitle' => $women->description, 'cta_label' => "Shop {$women->name}", 'link_url' => $url($women, '/shop'), 'image' => 'categories/women.webp', 'text_tone' => 'dark'] : null,
        ];

        $promo = [
            ['eyebrow' => 'New in', 'title' => 'Fresh to the shelf', 'cta_label' => 'Shop new arrivals', 'link_url' => '/shop?sort=newest', 'image' => 'site/signature-portrait.webp'],
            $attars ? ['eyebrow' => 'Pure perfume oils', 'title' => 'The '.Str::lower($attars->name).' edit', 'cta_label' => 'Shop '.Str::lower($attars->name), 'link_url' => $url($attars, '/shop'), 'image' => 'families/oud.webp'] : null,
            ['eyebrow' => 'Offers', 'title' => 'Treat yourself for less', 'cta_label' => 'Shop offers', 'link_url' => '/offers', 'image' => 'families/amber.webp'],
        ];

        foreach ([Banner::PLACEMENT_HERO => $hero, Banner::PLACEMENT_PROMO => $promo] as $placement => $banners) {
            if (Banner::where('placement', $placement)->exists()) {
                continue; // the admin already manages this placement
            }

            foreach (array_values(array_filter($banners)) as $order => $banner) {
                Banner::create([
                    ...$banner,
                    'placement' => $placement,
                    'sort_order' => $order,
                    'image' => $this->copy($banner['image'], 'banners'),
                    'video' => isset($banner['video']) ? $this->copy($banner['video'], 'banners') : null,
                ]);
            }
        }
    }

    private function siteMedia(): void
    {
        $slots = [
            'gift.banner' => ['videos/spray-poster.webp', 'videos/spray.mp4'],
            'guide.photo' => ['videos/spray-poster.webp'],
            'menu.promo' => ['site/signature-portrait.webp'],
            'shop.header' => ['site/signature-portrait.webp'],
            'offers.header' => ['videos/spray-poster.webp'],
            'login.panel' => ['videos/rose-bottle-poster.webp', 'videos/rose-bottle.mp4'],
            'about.story' => ['site/signature-portrait.webp'],
            'about.cta' => ['site/amber-light.webp'],
            'contact.panel' => ['videos/rose-bottle-poster.webp'],
        ];

        foreach ($slots as $slot => $files) {
            [$image, $video] = $files + [1 => null];

            if (SiteMedia::where('slot', $slot)->exists()) {
                continue;
            }

            SiteMedia::create([
                'slot' => $slot,
                'image' => $this->copy($image, 'site-media'),
                'video' => $video ? $this->copy($video, 'site-media') : null,
            ]);
        }
    }

    /** Copies a starter file onto the public disk under a fresh name; returns the stored path. */
    private function copy(string $file, string $folder): string
    {
        $path = $folder.'/'.Str::random(40).'.'.pathinfo($file, PATHINFO_EXTENSION);
        Storage::disk('public')->put($path, file_get_contents(self::SOURCE.'/'.$file));

        return $path;
    }
}
