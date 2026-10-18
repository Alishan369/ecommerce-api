<?php

namespace App\Http\Controllers;

use App\Http\Resources\ScentFamilyResource;
use App\Repositories\Interfaces\ScentFamilyRepositoryInterface;

/** Every scent family with its name, colours, photo and live product count. GET /v1/scent-families */
class ScentFamilyController extends Controller
{
    public function __invoke(ScentFamilyRepositoryInterface $families)
    {
        return ScentFamilyResource::collection($families->allWithProductCount());
    }
}
