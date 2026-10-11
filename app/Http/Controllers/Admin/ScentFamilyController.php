<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScentFamilyResource;
use App\Models\ScentFamily;
use App\Support\StoredImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Rename, recolour, reorder, show/hide and photograph the scent families. Photos are compressed to WebP. */
class ScentFamilyController extends Controller
{
    private const HEX = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function index()
    {
        return ScentFamilyResource::collection($this->all());
    }

    public function update(Request $request, string $key): ScentFamilyResource
    {
        $family = ScentFamily::where('key', $key)->firstOrFail();

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:40', Rule::unique('scent_families', 'label')->ignore($family->id)],
            'color_from' => ['sometimes', 'string', self::HEX],
            'color_to' => ['sometimes', 'string', self::HEX],
            'color_bg' => ['sometimes', 'string', self::HEX],
            'is_featured' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:min_width=300'],
            'remove_image' => ['sometimes', 'boolean'],
        ], [
            'label.unique' => 'Another family already uses this name.',
            'color_from.regex' => 'Use a colour like #a2714b.',
            'color_to.regex' => 'Use a colour like #3e2a1f.',
            'color_bg.regex' => 'Use a colour like #f3e7da.',
            'image.dimensions' => 'Use a photo at least 300px wide.',
            'image.max' => 'The photo must be 6 MB or smaller.',
        ]);

        if (isset($data['label'])) {
            $data['label'] = trim($data['label']);
        }
        foreach (['color_from', 'color_to', 'color_bg'] as $colour) {
            if (isset($data[$colour])) {
                $data[$colour] = strtolower($data[$colour]);
            }
        }

        StoredImage::update($family, $data, 'scent-families', ['image' => 1200]);

        return new ScentFamilyResource($this->all()->firstWhere('key', $key));
    }

    /** New display order: every family key, first to last. */
    public function reorder(Request $request): JsonResponse
    {
        $keys = ScentFamily::pluck('key')->all();
        $data = $request->validate([
            'keys' => ['required', 'array', 'size:'.count($keys)],
            'keys.*' => ['required', 'string', 'distinct', Rule::in($keys)],
        ], ['keys.size' => 'Send every family once, in the new order.']);

        DB::transaction(function () use ($data) {
            foreach ($data['keys'] as $position => $key) {
                ScentFamily::where('key', $key)->update(['sort_order' => $position]);
            }
        });

        return ScentFamilyResource::collection($this->all())->response();
    }

    private function all()
    {
        return ScentFamily::query()->select('scent_families.*')->withProductCount()->ordered()->get();
    }
}
