<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Razorpay client over Laravel's HTTP client (no SDK dependency).
 *
 * @see https://razorpay.com/docs/api/orders/create/
 * @see https://razorpay.com/docs/payments/server-integration/php/payment-gateway/build-integration/#verify-payment-signature
 */
class RazorpayGateway
{
    private const API_URL = 'https://api.razorpay.com/v1';

    public function __construct(
        private readonly ?string $keyId,
        private readonly ?string $keySecret,
        private readonly ?string $webhookSecret,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.razorpay.key_id'),
            config('services.razorpay.key_secret'),
            config('services.razorpay.webhook_secret'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->keyId) && filled($this->keySecret);
    }

    /** Public key — safe to send to the browser. */
    public function keyId(): ?string
    {
        return $this->keyId;
    }

    /**
     * @return array{id: string, amount: int, currency: string, status: string}
     */
    public function createOrder(int $amountInPaise, string $receipt, array $notes = []): array
    {
        try {
            $response = Http::withBasicAuth((string) $this->keyId, (string) $this->keySecret)
                ->acceptJson()
                ->timeout(15)
                // Retry only when the request never reached Razorpay, so a slow success can't create duplicates.
                ->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false)
                ->post(self::API_URL.'/orders', [
                    'amount' => $amountInPaise,
                    'currency' => 'INR',
                    'receipt' => $receipt,
                    'notes' => $notes,
                ]);
        } catch (ConnectionException) {
            throw new PaymentGatewayException('The payment service is unreachable right now. Please try again in a moment.');
        }

        if ($response->failed() || ! is_string($response->json('id'))) {
            // Log the error code only — never credentials or full payloads.
            Log::warning('Razorpay order creation failed', [
                'status' => $response->status(),
                'error' => $response->json('error.code'),
                'receipt' => $receipt,
            ]);

            throw new PaymentGatewayException('We couldn\'t start the payment. Please try again in a moment.');
        }

        return $response->json();
    }

    /** Signature returned to the browser by Checkout: HMAC-SHA256(order_id|payment_id, key_secret). */
    public function verifyPaymentSignature(string $gatewayOrderId, string $gatewayPaymentId, string $signature): bool
    {
        if (blank($this->keySecret)) {
            return false;
        }

        $expected = hash_hmac('sha256', $gatewayOrderId.'|'.$gatewayPaymentId, $this->keySecret);

        return hash_equals($expected, $signature);
    }

    /** Webhook signature: HMAC-SHA256(raw body, webhook_secret) in X-Razorpay-Signature. */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        if (blank($this->webhookSecret) || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $this->webhookSecret), $signature);
    }
}
