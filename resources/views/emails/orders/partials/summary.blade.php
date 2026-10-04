<x-mail::table>
| Item | Qty | Amount |
|:-----|:---:|-------:|
@foreach ($items as $item)
| {{ $item['name'] }} | {{ $item['quantity'] }} | {{ $item['amount'] }} |
@endforeach
| Subtotal | | {{ $subtotal }} |
@if ($discount)
| Coupon {{ $couponCode }} | | −{{ $discount }} |
@elseif ($couponCode)
| Coupon {{ $couponCode }} | | Free shipping |
@endif
| Shipping | | {{ $shipping }} |
| **Total** | | **{{ $total }}** |
</x-mail::table>

**Delivering to**<br>
{{ $order->shipping_name }} · {{ $order->shipping_phone }}<br>
{{ $order->shipping_address_line }}<br>
{{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_pincode }}
