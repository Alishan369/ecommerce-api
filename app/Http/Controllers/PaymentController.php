<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyRazorpayPaymentRequest;
use App\Http\Resources\OrderResource;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly OrderRepositoryInterface $orders,
    ) {}

    /** GET /payments/methods — what checkout may offer right now. */
    public function methods(): JsonResponse
    {
        return response()->json(['data' => $this->payments->availableMethods()]);
    }

    /** POST /orders/{orderNumber}/pay — (re)open payment for an unpaid online order. */
    public function initiate(string $orderNumber, Request $request): JsonResponse
    {
        // Same access rule as viewing the order: only the customer who placed it.
        $order = $this->orders->findForUser($orderNumber, $request->user());

        return response()->json(['data' => $this->payments->initiate($order)]);
    }

    /** POST /payments/razorpay/verify — browser callback after a successful Checkout. */
    public function verify(VerifyRazorpayPaymentRequest $request): OrderResource
    {
        $order = $this->orders->findForUser($request->validated('order_number'), $request->user());

        return new OrderResource($this->payments->confirm(
            $order,
            $request->validated('razorpay_order_id'),
            $request->validated('razorpay_payment_id'),
            $request->validated('razorpay_signature'),
        ));
    }

    /** POST /orders/{orderNumber}/cod — switch an unpaid online order to Cash on Delivery. */
    public function switchToCod(string $orderNumber, Request $request): OrderResource
    {
        $order = $this->orders->findForUser($orderNumber, $request->user());

        return new OrderResource($this->payments->switchToCod($order));
    }

    /** POST /payments/razorpay/webhook — signed server-to-server events. */
    public function webhook(Request $request, RazorpayGateway $razorpay): Response|JsonResponse
    {
        $payload = $request->getContent();

        if (! $razorpay->verifyWebhookSignature($payload, $request->header('X-Razorpay-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $this->payments->handleWebhook(json_decode($payload, true) ?: []);

        return response()->noContent();
    }
}
