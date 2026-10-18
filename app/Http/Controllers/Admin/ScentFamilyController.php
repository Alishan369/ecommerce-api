<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScentFamilyResource;
use App\Repositories\Interfaces\ScentFamilyRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rename, recolour, reorder, show/hide and photograph the scent families. Photos are compressed to WebP. */
class ScentFamilyController extends Controller
{
    private const HEX = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function __construct(private readonly ScentFamilyRepositoryInterface $families) {}

    public function index()
    {
        return ScentFamilyResource::collection($this->families->allWithProductCount());
    }

    public function update(Request $request, string $key): ScentFamilyResource
    {
        $family = $this->families->findByKey($key);

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

        $this->families->update($family, $data);

        return new ScentFamilyResource($this->families->allWithProductCount()->firstWhere('key', $key));
    }

    /** New display order: every family key, first to last. */
    public function reorder(Request $request): JsonResponse
    {
        $keys = $this->families->keys();
        $data = $request->validate([
            'keys' => ['required', 'array', 'size:'.count($keys)],
            'keys.*' => ['required', 'string', 'distinct', Rule::in($keys)],
        ], ['keys.size' => 'Send every family once, in the new order.']);

        $this->families->reorder($data['keys']);

        return ScentFamilyResource::collection($this->families->allWithProductCount())->response();
    }
}
