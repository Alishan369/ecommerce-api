<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Http\Resources\CartResource;
use App\Repositories\Interfaces\CartRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {}

    public function show(Request $request): CartResource
    {
        $cart = $this->cartRepository->getOrCreateCart($request);

        return new CartResource($cart);
    }

    public function addItem(AddToCartRequest $request): CartResource
    {
        $cart = $this->cartRepository->getOrCreateCart($request);

        $cart = $this->cartRepository->addItem(
            $cart,
            $request->validated(),
        );

        return new CartResource($cart);
    }

    private function resolveSessionToken(Request $request): ?string
    {
        if ($request->user()) {
            return null;
        }

        return $request->header('X-Cart-Token') ?? Str::uuid()->toString();
    }
}
