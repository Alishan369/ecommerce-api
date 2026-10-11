<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteMedia;
use App\Support\StoredImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Replace or reset the photo/video in a fixed storefront slot. Images are compressed to WebP. */
class SiteMediaController extends Controller
{
    public function index(): JsonResponse
    {
        $media = SiteMedia::query()->whereIn('slot', SiteMedia::SLOTS)->get()->map->toPublicArray()->values();

        return response()->json([
            'data' => $media,
            'slots' => SiteMedia::SLOTS,
            'video_slots' => SiteMedia::VIDEO_SLOTS,
        ]);
    }

    public function update(Request $request, string $slot): JsonResponse
    {
        abort_unless(in_array($slot, SiteMedia::SLOTS, true), 404, 'Unknown media slot.');
        $videoAllowed = in_array($slot, SiteMedia::VIDEO_SLOTS, true);

        $data = $request->validate([
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:min_width=400'],
            'remove_image' => ['sometimes', 'boolean'],
            'video' => [Rule::prohibitedIf(! $videoAllowed), 'sometimes', 'nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:20480'],
            'remove_video' => ['sometimes', 'boolean'],
        ], [
            'image.dimensions' => 'Use an image at least 400px wide.',
            'image.max' => 'The image must be 6 MB or smaller.',
            'video.prohibited' => 'This spot shows a photo only.',
            'video.max' => 'The video must be 20 MB or smaller — export it at 720p.',
            'video.mimetypes' => 'Upload an MP4 or WebM video.',
        ]);

        if ($data === []) {
            throw ValidationException::withMessages(['image' => 'Choose a photo or video to upload.']);
        }

        $media = SiteMedia::firstOrNew(['slot' => $slot]);
        // A video needs a still to show while it loads (and for reduced motion) — the photo.
        $fields = ['image' => 2000, 'video' => null];

        if (! $media->exists) {
            foreach ($fields as $field => $maxSize) {
                $data = StoredImage::fromInput($data, 'site-media', $field, $maxSize);
            }
            $media->fill($data)->save();
        } else {
            StoredImage::update($media, $data, 'site-media', $fields);
        }

        // Nothing left in the slot → back to the storefront default.
        if (! $media->image && ! $media->video) {
            $media->delete();

            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $media->fresh()->toPublicArray()]);
    }

    /** Reset to the storefront default. */
    public function destroy(string $slot): JsonResponse
    {
        SiteMedia::where('slot', $slot)->first()?->delete();

        return response()->json(['status' => 'success', 'message' => 'Reset to the default.']);
    }
}
