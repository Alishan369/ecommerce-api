<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Support\StoredImage;
use Illuminate\Http\JsonResponse;

/** Admin banner management. Images are compressed to WebP on upload. */
class BannerController extends Controller
{
    /** Longest side for banner images (sharp on large screens, small on disk); the video is stored as uploaded. */
    private const IMAGE_FIELDS = ['image' => 2000, 'image_mobile' => 1350, 'video' => null];

    public function index()
    {
        return BannerResource::collection(Banner::query()->orderBy('placement')->ordered()->get());
    }

    public function store(BannerRequest $request): JsonResponse
    {
        $data = $request->validated();
        foreach (self::IMAGE_FIELDS as $field => $maxSize) {
            $data = StoredImage::fromInput($data, 'banners', $field, $maxSize);
        }

        $banner = Banner::create($data);

        return (new BannerResource($banner->fresh()))->response()->setStatusCode(201);
    }

    public function update(BannerRequest $request, Banner $banner): BannerResource
    {
        StoredImage::update($banner, $request->validated(), 'banners', self::IMAGE_FIELDS);

        return new BannerResource($banner->fresh());
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return response()->json(['status' => 'success', 'message' => 'Banner deleted.']);
    }
}
