<x-mail::message>
# {{ $heading }}

Hi {{ $firstName }}, {{ lcfirst($message) }}

<x-mail::panel>
**Order {{ $order->order_number }}**<br>
Placed on {{ $placedOn }}<br>
Status: **{{ ucfirst($order->status) }}**
</x-mail::panel>

@include('emails.orders.partials.summary')

<x-mail::button :url="$orderUrl" color="primary">
View your order
</x-mail::button>

Need help? [Contact us]({{ $contactUrl }}).

With love,<br>
The {{ config('app.name') }} team
</x-mail::message>
