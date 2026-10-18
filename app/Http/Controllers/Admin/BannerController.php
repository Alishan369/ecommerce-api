<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use Illuminate\Http\JsonResponse;

/** Admin banner management. Images are compressed to WebP on upload. */
class BannerController extends Controller
{
    public function __construct(private readonly BannerRepositoryInterface $banners) {}

    public function index()
    {
        return BannerResource::collection($this->banners->all());
    }

    public function store(BannerRequest $request): JsonResponse
    {
        return (new BannerResource($this->banners->store($request->validated())))->response()->setStatusCode(201);
    }

    public function update(BannerRequest $request, Banner $banner): BannerResource
    {
        return new BannerResource($this->banners->update($banner, $request->validated()));
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $this->banners->delete($banner);

        return response()->json(['status' => 'success', 'message' => 'Banner deleted.']);
    }
}
