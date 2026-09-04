<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\Product;
use App\Repositories\Interfaces\CartRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartRepository implements CartRepositoryInterface
{
    public function getOrCreateCart($request): Cart
    {
        // $userId = $request->user()?->id;
        $userId = null;
        $sessionToken = $userId ? null : ($request->header('x-guest-token') ?? Str::uuid()->toString());

        $cart = Cart::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(! $userId, fn ($q) => $q->where('session_token', $sessionToken))
            ->first();

        if ($cart) {
            return $cart;
        }

        return Cart::create([
            'user_id' => $userId,
            'session_token' => $userId ? null : $sessionToken,
        ]);
    }

    public function addItem(Cart $cart, array $requestData): Cart
    {
        $productId = $requestData['product_id'];
        $quantity = $requestData['quantity'];
        $product = Product::findOrFail($productId);

        $existingItem = $cart->items()->where('product_id', $productId)->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $quantity;

            if ($product->stock < $newQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Not enough stock available. Only '.$product->stock.' left.',
                ]);
            }

            $existingItem->update(['quantity' => $newQuantity]);
        } else {
            if ($product->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Not enough stock available. Only '.$product->stock.' left.',
                ]);
            }

            $cart->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $product->sale_price ?? $product->price,
            ]);
        }

        return $cart->fresh('items.product');
    }
}
