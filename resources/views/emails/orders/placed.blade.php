<x-mail::message>
# Thank you, {{ $firstName }}!

We've received your order and we're getting it ready. Here's your summary.

<x-mail::panel>
**Order {{ $order->order_number }}**<br>
Placed on {{ $placedOn }}<br>
{{ $paymentLine }}
</x-mail::panel>

@include('emails.orders.partials.summary')

<x-mail::button :url="$orderUrl" color="primary">
View your order
</x-mail::button>

You can follow every step of this order from your account. Need help? [Contact us]({{ $contactUrl }}).

With love,<br>
The {{ config('app.name') }} team
</x-mail::message>
