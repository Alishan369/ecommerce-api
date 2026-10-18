<?php

namespace App\Http\Controllers;

use App\Models\SiteMedia;
use App\Repositories\Interfaces\SiteMediaRepositoryInterface;
use Illuminate\Http\JsonResponse;

/** Admin-replaced storefront media, keyed by slot. Slots not listed use the storefront default. GET /v1/media */
class SiteMediaController extends Controller
{
    public function __invoke(SiteMediaRepositoryInterface $siteMedia): JsonResponse
    {
        $media = $siteMedia->all()
            ->mapWithKeys(fn (SiteMedia $m) => [$m->slot => $m->toPublicArray()]);

        return response()->json(['data' => (object) $media->all()]);
    }
}
