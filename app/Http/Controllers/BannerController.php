<?php

namespace App\Http\Controllers;

use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Public, live banners for the storefront. GET /v1/banners?placement=hero */
class BannerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'placement' => ['sometimes', Rule::in(Banner::PLACEMENTS)],
        ]);

        $banners = Banner::query()
            ->live()
            ->when($data['placement'] ?? null, fn ($q, $placement) => $q->where('placement', $placement))
            ->ordered()
            ->limit(12)
            ->get();

        return BannerResource::collection($banners);
    }
}
