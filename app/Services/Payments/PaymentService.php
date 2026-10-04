<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\OrderNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Online payment lifecycle for orders.
 *
 * Checkout creates the order first (stock reserved, cart emptied), then a
 * Razorpay order for the exact DB total. The order becomes "paid" only after a
 * server-side signature check (browser callback) or a signed webhook — whichever
 * arrives first; the other is a no-op. Unpaid online orders expire after
 * services.razorpay.unpaid_order_ttl minutes and their stock is released.
 */
class PaymentService
{
    public const GATEWAY = 'razorpay';

    public function __construct(
        private readonly RazorpayGateway $razorpay,
        private readonly OrderRepositoryInterface $orders,
        private readonly OrderNotifier $notifier,
    ) {}

    /** Methods offered at checkout — online first when configured, COD always. */
    public function availableMethods(): array
    {
        $methods = [];

        if ($this->razorpay->isConfigured()) {
            $methods[] = [
                'code' => self::GATEWAY,
                'label' => 'Pay online',
                'description' => 'UPI, cards, net banking and wallets — processed securely by Razorpay.',
            ];
        }

        $methods[] = [
            'code' => 'cod',
            'label' => 'Cash on Delivery',
            'description' => 'Pay in cash when your order arrives.',
        ];

        return $methods;
    }

    /** @return array<string> */
    public function methodCodes(): array
    {
        return array_column($this->availableMethods(), 'code');
    }

    /**
     * Returns what the Razorpay Checkout widget needs, reusing an open gateway
     * order for retries so one order doesn't spawn many gateway orders.
     */
    public function initiate(Order $order): array
    {
        if (! $order->awaitingPayment()) {
            throw ValidationException::withMessages(['order' => 'This order is not awaiting payment.']);
        }

        if (! $this->razorpay->isConfigured()) {
            throw new PaymentGatewayException('Online payments are not available right now. You can switch this order to Cash on Delivery.');
        }

        $amount = $order->amountInPaise();

        $payment = $order->payments()
            ->where('gateway', self::GATEWAY)
            ->where('status', '!=', Payment::STATUS_PAID)
            ->where('amount', $amount)
            ->latest('id')
            ->first();

        if (! $payment) {
            $gatewayOrder = $this->razorpay->createOrder($amount, $order->order_number, [
                'order_number' => $order->order_number,
            ]);

            $payment = $order->payments()->create([
                'gateway' => self::GATEWAY,
                'gateway_order_id' => $gatewayOrder['id'],
                'amount' => $amount,
                'currency' => 'INR',
                'status' => Payment::STATUS_CREATED,
            ]);
        }

        return [
            'gateway' => self::GATEWAY,
            'key_id' => $this->razorpay->keyId(),
            'order_id' => $payment->gateway_order_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'prefill' => [
                'name' => $order->shipping_name,
                'email' => $order->email,
                'contact' => $order->shipping_phone,
            ],
        ];
    }

    /** Browser callback after Checkout succeeds. Trusts nothing but the HMAC signature. */
    public function confirm(Order $order, string $gatewayOrderId, string $gatewayPaymentId, string $signature): Order
    {
        $payment = $order->payments()->where('gateway_order_id', $gatewayOrderId)->first();

        if (! $payment || ! $this->razorpay->verifyPaymentSignature($gatewayOrderId, $gatewayPaymentId, $signature)) {
            Log::warning('Razorpay payment verification failed', ['order' => $order->order_number]);

            throw ValidationException::withMessages([
                'payment' => 'We couldn\'t verify this payment. If money was deducted, it will be confirmed automatically or refunded by your bank.',
            ]);
        }

        $this->markPaid($payment, $gatewayPaymentId);

        return $order->fresh('items');
    }

    /** Signed webhook events — the safety net when the browser never returns. */
    public function handleWebhook(array $event): void
    {
        $type = $event['event'] ?? null;
        $entity = data_get($event, 'payload.payment.entity');

        if (! is_array($entity) || empty($entity['order_id'])) {
            return;
        }

        $payment = Payment::query()
            ->where('gateway', self::GATEWAY)
            ->where('gateway_order_id', $entity['order_id'])
            ->first();

        if (! $payment) {
            return; // not ours (or created outside this store) — acknowledge and ignore
        }

        if (in_array($type, ['payment.captured', 'order.paid'], true)) {
            if ((int) ($entity['amount'] ?? 0) !== $payment->amount) {
                Log::error('Razorpay webhook amount mismatch', ['payment' => $payment->id, 'event' => $type]);

                return;
            }

            $this->markPaid($payment, (string) $entity['id']);
        } elseif ($type === 'payment.failed') {
            $this->markFailed($payment, $entity['error_code'] ?? null, $entity['error_description'] ?? null);
        }
    }

    /** Customer gives up on paying online and chooses Cash on Delivery for the same order. */
    public function switchToCod(Order $order): Order
    {
        if (! $order->awaitingPayment()) {
            throw ValidationException::withMessages(['order' => 'This order can no longer be changed.']);
        }

        $order->update(['payment_method' => 'cod', 'payment_status' => 'pending']);
        $this->notifier->placed($order); // now a confirmed COD order

        return $order->fresh('items');
    }

    /** Cancels online orders left unpaid past the payment window and releases their stock. */
    public function expireUnpaid(): int
    {
        $cutoff = now()->subMinutes((int) config('services.razorpay.unpaid_order_ttl', 30));
        $expired = 0;

        Order::query()
            ->where('payment_method', '!=', 'cod')
            ->where('status', 'pending')
            ->whereNotIn('payment_status', ['paid', 'expired'])
            ->where('placed_at', '<', $cutoff)
            ->pluck('id')
            ->each(function (int $orderId) use (&$expired) {
                DB::transaction(function () use ($orderId, &$expired) {
                    // Re-check under lock: a webhook may have paid it a moment ago.
                    $order = Order::query()->lockForUpdate()->find($orderId);

                    if (! $order || ! $order->awaitingPayment()) {
                        return;
                    }

                    $this->orders->updateStatus($order, 'cancelled'); // restocks
                    $order->update(['payment_status' => 'expired']);
                    $expired++;
                });
            });

        return $expired;
    }

    private function markPaid(Payment $payment, string $gatewayPaymentId): void
    {
        DB::transaction(function () use ($payment, $gatewayPaymentId) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === Payment::STATUS_PAID) {
                return; // browser callback and webhook both arrive — second one is a no-op
            }

            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            $payment->update([
                'status' => Payment::STATUS_PAID,
                'gateway_payment_id' => $gatewayPaymentId,
                'paid_at' => now(),
                'error_code' => null,
                'error_description' => null,
            ]);

            $updates = ['payment_status' => 'paid'];

            if ($order->status === 'pending') {
                $updates['status'] = 'confirmed';
            } elseif ($order->status === 'cancelled') {
                // Paid after the window closed (stock already released) — needs a manual refund.
                Log::warning('Payment captured for a cancelled order — refund required', [
                    'order' => $order->order_number,
                    'payment' => $gatewayPaymentId,
                ]);
            }

            $order->update($updates);

            // First (and only) confirmation for an online order. Queued after commit;
            // the duplicate callback/webhook path returned early above, so no double email.
            if ($order->status !== 'cancelled') {
                $this->notifier->placed($order);
            }
        });
    }

    private function markFailed(Payment $payment, ?string $code, ?string $description): void
    {
        DB::transaction(function () use ($payment, $code, $description) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === Payment::STATUS_PAID) {
                return; // a later attempt on the same gateway order already succeeded
            }

            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'error_code' => $code ? Str::limit($code, 100, '') : null,
                'error_description' => $description ? Str::limit($description, 250) : null,
            ]);

            Order::query()
                ->whereKey($payment->order_id)
                ->whereNotIn('payment_status', ['paid', 'expired'])
                ->update(['payment_status' => 'failed']);
        });
    }
}
