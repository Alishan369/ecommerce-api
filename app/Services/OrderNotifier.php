<?php

namespace App\Services;

use App\Mail\OrderPlaced;
use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Queues customer emails for order events. Emails are a courtesy: a mail or
 * queue failure is logged and never breaks checkout, payment or an admin action.
 */
class OrderNotifier
{
    /** Status changes worth an email (pending → confirmed → processing are internal steps). */
    public const NOTIFY_STATUSES = ['shipped', 'delivered', 'cancelled'];

    /** COD order placed, online payment confirmed, or switched to COD. */
    public function placed(Order $order): void
    {
        $this->send($order, fn () => Mail::to($order->email)->queue(new OrderPlaced($order)));
    }

    public function statusChanged(Order $order): void
    {
        if (in_array($order->status, self::NOTIFY_STATUSES, true)) {
            $this->send($order, fn () => Mail::to($order->email)->queue(new OrderStatusUpdated($order)));
        }
    }

    private function send(Order $order, callable $queue): void
    {
        if (! $order->email) {
            return;
        }

        try {
            $queue();
        } catch (Throwable $e) {
            Log::error('Could not queue order email', ['order' => $order->order_number, 'error' => $e->getMessage()]);
        }
    }
}
