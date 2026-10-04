<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Repositories\CartRepository;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {}

    public function show(Request $request): CartResource
    {
        // Reading the cart must never create one — otherwise every page view inserts a row.
        $cart = $this->cartRepository->findCart($request) ?? $this->emptyCart();

        return new CartResource($cart->loadMissing('items.product'));
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

    public function updateItem(Request $request, int $productId): CartResource
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartRepository::MAX_QUANTITY_PER_ITEM],
        ]);
        $cart = $this->cartRepository->findCart($request) ?? abort(404);

        return new CartResource($this->cartRepository->updateItem($cart, $productId, $data['quantity']));
    }

    public function removeItem(Request $request, int $productId): CartResource
    {
        $cart = $this->cartRepository->findCart($request) ?? abort(404);

        return new CartResource($this->cartRepository->removeItem($cart, $productId));
    }

    /** POST /cart/coupon — validates and applies a code to the current cart. */
    public function applyCoupon(Request $request, CouponService $coupons): CartResource
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']], ['code.required' => 'Enter a coupon code.']);

        $cart = $this->cartRepository->findCart($request);
        if (! $cart || $cart->items()->doesntExist()) {
            throw ValidationException::withMessages(['code' => 'Add a perfume to your cart before applying a coupon.']);
        }

        $cart->loadMissing('items.product');
        $coupon = $coupons->findByCode($data['code']);
        // Same message for "doesn't exist" and "switched off", so codes can't be probed.
        $reason = $coupon
            ? $coupons->ineligibilityReason($coupon, $cart->payableSubtotal(), $request->user('sanctum'))
            : 'This coupon code isn\'t valid.';

        if ($reason) {
            throw ValidationException::withMessages(['code' => $reason]);
        }

        $cart->update(['coupon_code' => $coupon->code]);

        return new CartResource($cart);
    }

    /** DELETE /cart/coupon */
    public function removeCoupon(Request $request): CartResource
    {
        $cart = $this->cartRepository->findCart($request) ?? $this->emptyCart();

        if ($cart->exists) {
            $cart->update(['coupon_code' => null]);
        }

        return new CartResource($cart->loadMissing('items.product'));
    }

    public function clear(Request $request)
    {
        if ($cart = $this->cartRepository->findCart($request)) {
            $this->cartRepository->clear($cart);
        }

        return response()->noContent();
    }

    private function emptyCart(): Cart
    {
        return (new Cart)->setRelation('items', collect());
    }
}
