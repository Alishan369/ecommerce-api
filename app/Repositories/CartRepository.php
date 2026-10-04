<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\Product;
use App\Repositories\Interfaces\CartRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartRepository implements CartRepositoryInterface
{
    public const SESSION_HEADER = 'X-Cart-Session-Id';

    public const MAX_QUANTITY_PER_ITEM = 20;

    public function findCart(Request $request): ?Cart
    {
        // Cart routes have no auth middleware, so resolve the bearer token explicitly.
        $user = $request->user('sanctum');
        $guestToken = $this->guestToken($request);

        if ($user) {
            $cart = Cart::query()->where('user_id', $user->id)->first();

            return $guestToken ? $this->mergeGuestCart($guestToken, $user->id, $cart) : $cart;
        }

        if (! $guestToken) {
            return null;
        }

        return Cart::query()
            ->whereNull('user_id')
            ->where('session_token', $guestToken)
            ->first();
    }

    public function getOrCreateCart(Request $request): Cart
    {
        if ($cart = $this->findCart($request)) {
            return $cart;
        }

        $user = $request->user('sanctum');
        $guestToken = $this->guestToken($request);

        if (! $user && ! $guestToken) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart session has expired. Please refresh the page and try again.',
            ]);
        }

        return Cart::create([
            'user_id' => $user?->id,
            'session_token' => $user ? null : $guestToken,
        ]);
    }

    public function guestToken(Request $request): ?string
    {
        $token = $request->header(self::SESSION_HEADER);

        // Only accept the format the storefront generates, so arbitrary strings can't mint carts.
        return is_string($token) && preg_match('/^cart_[A-Za-z0-9-]{16,64}$/', $token) === 1
            ? $token
            : null;
    }

    public function addItem(Cart $cart, array $requestData): Cart
    {
        $productId = (int) $requestData['product_id'];
        $quantity = (int) $requestData['quantity'];

        $product = Product::query()->whereKey($productId)->where('is_active', true)->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is no longer available.',
            ]);
        }

        $existingItem = $cart->items()->where('product_id', $productId)->first();
        $newQuantity = ($existingItem?->quantity ?? 0) + $quantity;

        $this->guardQuantity($product, $newQuantity);

        if ($existingItem) {
            $existingItem->update(['quantity' => $newQuantity]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $product->sale_price ?? $product->price,
            ]);
        }

        return $cart->fresh('items.product');
    }

    public function updateItem(Cart $cart, int $productId, int $quantity): Cart
    {
        $item = $cart->items()->where('product_id', $productId)->firstOrFail();
        $product = Product::findOrFail($productId);

        $this->guardQuantity($product, $quantity);

        $item->update(['quantity' => $quantity]);

        return $cart->fresh('items.product');
    }

    public function removeItem(Cart $cart, int $productId): Cart
    {
        $cart->items()->where('product_id', $productId)->firstOrFail()->delete();

        return $cart->fresh('items.product');
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    private function guardQuantity(Product $product, int $quantity): void
    {
        if ($quantity > self::MAX_QUANTITY_PER_ITEM) {
            throw ValidationException::withMessages([
                'quantity' => 'You can add up to '.self::MAX_QUANTITY_PER_ITEM.' of this item.',
            ]);
        }

        if ($product->stock < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => $product->stock > 0
                    ? 'Only '.$product->stock.' left in stock.'
                    : 'This product is out of stock.',
            ]);
        }
    }

    /**
     * After sign-in the storefront still sends its guest session header once;
     * fold that guest cart into the user's cart so nothing is lost.
     */
    private function mergeGuestCart(string $guestToken, int $userId, ?Cart $userCart): ?Cart
    {
        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where('session_token', $guestToken)
            ->with('items')
            ->first();

        if (! $guestCart) {
            return $userCart;
        }

        if (! $userCart) {
            $guestCart->update(['user_id' => $userId, 'session_token' => null]);

            return $guestCart;
        }

        DB::transaction(function () use ($guestCart, $userCart) {
            foreach ($guestCart->items as $item) {
                $existing = $userCart->items()->where('product_id', $item->product_id)->first();

                if ($existing) {
                    $existing->update([
                        'quantity' => min(self::MAX_QUANTITY_PER_ITEM, $existing->quantity + $item->quantity),
                    ]);
                } else {
                    $userCart->items()->create($item->only(['product_id', 'quantity', 'price']));
                }
            }

            // Keep a coupon the shopper applied before signing in (theirs wins if both have one).
            if ($guestCart->coupon_code && ! $userCart->coupon_code) {
                $userCart->update(['coupon_code' => $guestCart->coupon_code]);
            }

            $guestCart->delete();
        });

        return $userCart;
    }
}
