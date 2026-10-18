<?php

namespace App\Http\Controllers;

use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Public, live banners for the storefront. GET /v1/banners?placement=hero */
class BannerController extends Controller
{
    public function __construct(private readonly BannerRepositoryInterface $banners) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'placement' => ['sometimes', Rule::in(Banner::PLACEMENTS)],
        ]);

        return BannerResource::collection($this->banners->live($data['placement'] ?? null));
    }
}
