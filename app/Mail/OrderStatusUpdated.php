<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Shipped / delivered / cancelled updates (see OrderNotifier::NOTIFY_STATUSES). */
class OrderStatusUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $number = $this->order->order_number;

        return new Envelope(subject: match ($this->order->status) {
            'shipped' => "Your order {$number} is on its way",
            'delivered' => "Your order {$number} has been delivered",
            'cancelled' => "Your order {$number} has been cancelled",
            default => "Update on your order {$number}",
        });
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing('items');

        [$heading, $message] = match ($order->status) {
            'shipped' => ['It\'s on its way!', 'Good news — your order has left our warehouse and is with the courier.'],
            'delivered' => ['Delivered — enjoy!', 'Your order has been delivered. We hope you love your new fragrance.'],
            'cancelled' => ['Your order was cancelled', $this->cancellationReason($order)],
            default => ['Your order was updated', 'The status of your order has changed.'],
        };

        return new Content(
            markdown: 'emails.orders.status',
            with: OrderEmail::data($order) + compact('heading', 'message'),
        );
    }

    private function cancellationReason(Order $order): string
    {
        if ($order->payment_status === 'expired') {
            return 'The online payment wasn\'t completed in time, so the order was cancelled and the items were released. You\'re welcome to order again any time.';
        }

        if ($order->payment_status === 'paid') {
            return 'Because this order was paid online, our team has been notified to process your refund to the original payment method.';
        }

        return 'The order has been cancelled and nothing will be charged. You\'re welcome to order again any time.';
    }
}
