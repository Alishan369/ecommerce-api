<?php

namespace App\Repositories;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\CouponService;
use App\Services\OrderNotifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderRepository implements OrderRepositoryInterface
{
    /** Statuses a customer may still cancel from (nothing has left the warehouse yet). */
    private const CUSTOMER_CANCELLABLE = ['pending', 'confirmed'];

    private const ITEM_RELATIONS = ['items.product:id,slug,image,fragrance_family,concentration,size_ml'];

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CouponService $coupons,
        private readonly OrderNotifier $notifier,
    ) {}

    /** Checkout is behind auth:sanctum — every order belongs to a signed-in customer. */
    public function checkout(Request $request, array $data): Order
    {
        $user = $request->user();
        $saveAddress = (bool) ($data['save_address'] ?? false);
        unset($data['save_address']);

        return DB::transaction(function () use ($request, $data, $user, $saveAddress) {
            $cart = $this->cartRepository->findCart($request);

            if (! $cart) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $items = $cart->items()->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $products = Product::query()->whereIn('id', $items->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');
            $subtotal = 0.0;
            $lineItems = [];

            foreach ($items as $item) {
                $product = $products->get($item->product_id);

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages(['cart' => 'One or more products are no longer available.']);
                }

                if ($product->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "{$product->name} has only {$product->stock} item(s) available.",
                    ]);
                }

                $unitPrice = (float) ($product->sale_price ?? $product->price);
                $lineSubtotal = round($unitPrice * $item->quantity, 2);
                $subtotal += $lineSubtotal;
                $lineItems[] = compact('product', 'unitPrice', 'lineSubtotal', 'item');
            }

            // Coupon: re-checked here under a row lock, with locking reads for usage counts,
            // so simultaneous checkouts can't push a coupon past its usage limit.
            $coupon = null;
            if ($cart->coupon_code) {
                $coupon = Coupon::query()->where('code', $cart->coupon_code)->lockForUpdate()->first();

                if (! $coupon) {
                    throw ValidationException::withMessages([
                        'coupon' => "The coupon {$cart->coupon_code} is no longer valid. Remove it to continue.",
                    ]);
                }
            }

            $totals = $this->coupons->totals(round($subtotal, 2), $coupon, $user, locking: true);

            if ($coupon && $totals['coupon_error']) {
                // Never silently charge more than the cart showed — let the customer decide.
                throw ValidationException::withMessages([
                    'coupon' => "{$coupon->code}: {$totals['coupon_error']} Remove the coupon to continue.",
                ]);
            }

            $order = Order::create([
                ...$data,
                'email' => $user->email,
                'user_id' => $user->id,
                'guest_token' => null,
                'order_number' => $this->newOrderNumber(),
                'subtotal' => $totals['subtotal'],
                'shipping_amount' => $totals['shipping'],
                'discount_amount' => $totals['discount'],
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'total' => $totals['total'],
                'status' => 'pending',
                'payment_status' => 'pending',
                'placed_at' => now(),
            ]);

            foreach ($lineItems as $line) {
                $line['product']->decrement('stock', $line['item']->quantity);
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'product_sku' => $line['product']->sku,
                    'unit_price' => $line['unitPrice'],
                    'quantity' => $line['item']->quantity,
                    'subtotal' => $line['lineSubtotal'],
                ]);
            }

            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);

            if ($saveAddress) {
                $this->rememberAddress($user, $data);
            }

            return $order->load(self::ITEM_RELATIONS);
        }, 3);
    }

    /** Customers can only ever see their own orders; anything else is a 404. */
    public function findForUser(string $orderNumber, User $user): Order
    {
        return Order::query()
            ->with(self::ITEM_RELATIONS)
            ->where('order_number', $orderNumber)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function lookup(string $orderNumber, string $email): Order
    {
        $order = Order::query()
            ->with(self::ITEM_RELATIONS)
            ->where('order_number', $orderNumber)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        // Same response whether the number or the email is wrong.
        abort_unless($order, 404, 'We could not find an order with those details.');

        return $order;
    }

    public function getForUser(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return Order::query()
            ->with(self::ITEM_RELATIONS)
            ->where('user_id', $userId)
            ->latest('placed_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 50));
    }

    public function getAdminOrders(array $filters = []): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        return Order::query()
            ->withCount('items')
            ->with([...self::ITEM_RELATIONS, 'user:id,name,email,phone'])
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $q, string $status) => $q->where('payment_status', $status))
            // Same definition as the dashboard's "to fulfil": paid or COD, not yet shipped.
            ->when(! empty($filters['to_fulfil']), fn (Builder $q) => $q
                ->whereIn('status', ['pending', 'confirmed', 'processing'])
                ->where(fn (Builder $w) => $w->where('payment_status', 'paid')->orWhere('payment_method', 'cod')))
            ->when($search, function (Builder $q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->where('order_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('shipping_name', 'like', $like)
                    ->orWhere('shipping_phone', 'like', $like)
                );
            })
            ->latest('placed_at')
            ->orderByDesc('id')
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100))
            ->withQueryString();
    }

    public function updateStatus(Order $order, string $status): Order
    {
        if ($order->status === $status) {
            return $order->fresh(self::ITEM_RELATIONS);
        }

        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Cancelled orders cannot be reopened. Ask the customer to place a new order.',
            ]);
        }

        if ($order->status === 'delivered') {
            throw ValidationException::withMessages([
                'status' => 'Delivered orders are final. Handle returns as a separate refund.',
            ]);
        }

        DB::transaction(function () use ($order, $status) {
            // Cancelling returns the reserved stock.
            if ($status === 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
                    }
                }
            }

            $updates = ['status' => $status];

            // Cash on Delivery is collected by the courier at the door.
            if ($status === 'delivered' && $order->payment_method === 'cod') {
                $updates['payment_status'] = 'paid';
            }

            $order->update($updates);
        });

        $updated = $order->fresh(self::ITEM_RELATIONS);
        $this->notifier->statusChanged($updated); // shipped / delivered / cancelled emails

        return $updated;
    }

    public function cancelForCustomer(Order $order): Order
    {
        if (! in_array($order->status, self::CUSTOMER_CANCELLABLE, true)) {
            throw ValidationException::withMessages([
                'order' => 'This order can no longer be cancelled online — it may already be packed or shipped. Please contact support.',
            ]);
        }

        if ($order->payment_status === 'paid') {
            // Money has been captured — a refund needs a human, so route it to support.
            throw ValidationException::withMessages([
                'order' => 'This order is already paid. Please contact support to cancel it and receive a refund.',
            ]);
        }

        return $this->updateStatus($order, 'cancelled');
    }

    /** Saves the checkout address to the address book unless an identical one already exists. */
    private function rememberAddress(User $user, array $data): void
    {
        $fields = [
            'name' => $data['shipping_name'],
            'phone' => $data['shipping_phone'],
            'address_line' => $data['shipping_address_line'],
            'city' => $data['shipping_city'],
            'state' => $data['shipping_state'],
            'pincode' => $data['shipping_pincode'],
        ];

        $exists = Address::query()->where('user_id', $user->id)->where($fields)->exists();

        if (! $exists) {
            $isFirst = ! Address::query()->where('user_id', $user->id)->exists();
            Address::create([...$fields, 'user_id' => $user->id, 'is_default' => $isFirst]);
        }
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
