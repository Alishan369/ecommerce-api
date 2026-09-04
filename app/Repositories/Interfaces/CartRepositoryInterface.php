<?php

namespace App\Repositories\Interfaces;

use App\Models\Cart;

interface CartRepositoryInterface
{
    public function getOrCreateCart($request): Cart;

    public function addItem(Cart $cart, array $request): Cart;
}
