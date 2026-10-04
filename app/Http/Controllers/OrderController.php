<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutOrderRequest;
use App\Http\Requests\LookupOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\OrderNotifier;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    /** POST /orders — signed-in customers only. */
    public function store(CheckoutOrderRequest $request, PaymentService $payments, OrderNotifier $notifier)
    {
        $order = $this->orders->checkout($request, $request->validated());

        // Online orders get a gateway order straight away. If the gateway is down the
        // order still stands (stock reserved) — the customer can retry or switch to COD.
        // Their confirmation email is sent once payment is verified (PaymentService).
        $payment = null;
        if (! $order->isOnlinePayment()) {
            $notifier->placed($order);
        } else {
            try {
                $payment = $payments->initiate($order);
            } catch (PaymentGatewayException) {
                $payment = null;
            }
        }

        return (new OrderResource($order))
            ->additional(['payment' => $payment])
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $orderNumber, Request $request): OrderResource
    {
        return new OrderResource($this->orders->findForUser($orderNumber, $request->user()));
    }

    /** Public "track your order" — needs the order number and the email it was placed with. */
    public function lookup(LookupOrderRequest $request): OrderResource
    {
        return new OrderResource($this->orders->lookup(
            $request->validated('order_number'),
            $request->validated('email'),
        ));
    }

    public function index(Request $request)
    {
        $validated = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);

        return OrderResource::collection(
            $this->orders->getForUser($request->user()->id, (int) ($validated['per_page'] ?? 10))
        );
    }

    /** POST /orders/{orderNumber}/cancel — customer cancels before the order ships. */
    public function cancel(string $orderNumber, Request $request): OrderResource
    {
        $order = $this->orders->findForUser($orderNumber, $request->user());

        return new OrderResource($this->orders->cancelForCustomer($order));
    }

    public function adminIndex(Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(Order::STATUSES)],
            'payment_status' => ['sometimes', 'nullable', Rule::in(['pending', 'paid', 'failed', 'expired'])],
            'to_fulfil' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return OrderResource::collection($this->orders->getAdminOrders($filters));
    }

    public function adminShow(Order $order): OrderResource
    {
        return new OrderResource($order->load([
            'items.product:id,slug,image,fragrance_family,concentration,size_ml',
            'user:id,name,email,phone',
        ]));
    }

    public function update(Order $order, UpdateOrderStatusRequest $request): OrderResource
    {
        return new OrderResource($this->orders->updateStatus($order, $request->validated('status')));
    }
}
