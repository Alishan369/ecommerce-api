<?php

namespace App\Repositories\Interfaces;

use App\Models\Cart;
use Illuminate\Http\Request;

interface CartRepositoryInterface
{
    /** Existing cart for this user / guest session, or null. Never creates rows. */
    public function findCart(Request $request): ?Cart;

    public function getOrCreateCart(Request $request): Cart;

    /** Validated guest session token from the request header, or null. */
    public function guestToken(Request $request): ?string;

    public function addItem(Cart $cart, array $request): Cart;

    public function updateItem(Cart $cart, int $productId, int $quantity): Cart;

    public function removeItem(Cart $cart, int $productId): Cart;

    public function clear(Cart $cart): void;
}
