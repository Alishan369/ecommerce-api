<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "We've got your order" — sent when a Cash on Delivery order is placed, or
 * when an online payment is confirmed (never for an unpaid online order).
 */
class OrderPlaced extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        // Orders are written inside DB transactions — only send once they're committed.
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order {$this->order->order_number} confirmed — thank you!");
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing('items');

        return new Content(
            markdown: 'emails.orders.placed',
            with: OrderEmail::data($order) + [
                'paymentLine' => $order->payment_method === 'cod'
                    ? 'Cash on Delivery — please keep '.OrderEmail::money((float) $order->total).' ready when it arrives.'
                    : 'Paid online — thank you, your payment was received.',
            ],
        );
    }
}
