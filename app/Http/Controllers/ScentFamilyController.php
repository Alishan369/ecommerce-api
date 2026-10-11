<?php

namespace App\Http\Controllers;

use App\Http\Resources\ScentFamilyResource;
use App\Models\ScentFamily;

/** Every scent family with its name, colours, photo and live product count. GET /v1/scent-families */
class ScentFamilyController extends Controller
{
    public function __invoke()
    {
        return ScentFamilyResource::collection(
            ScentFamily::query()->select('scent_families.*')->withProductCount()->ordered()->get()
        );
    }
}
