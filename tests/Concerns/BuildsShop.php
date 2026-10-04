<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/** Small builders shared by the API feature tests. */
trait BuildsShop
{
    /** A product in its own category: MRP ₹1,000, sale ₹800, 5 in stock. */
    protected function product(array $attrs = []): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 1000,
            'sale_price' => 800,
            'stock' => 5,
            'is_active' => true,
            ...$attrs,
        ]);
    }

    protected function customer(array $attrs = []): User
    {
        return User::factory()->create(['role' => 'customer', ...$attrs]);
    }

    protected function admin(array $attrs = []): User
    {
        return User::factory()->create(['role' => 'admin', ...$attrs]);
    }

    /** Checkout payload (Cash on Delivery). */
    protected function shipping(array $overrides = []): array
    {
        return [
            'shipping_name' => 'Ravi Kumar',
            'shipping_phone' => '+91 98765 43210',
            'shipping_address_line' => '12 MG Road, Indiranagar',
            'shipping_city' => 'Bengaluru',
            'shipping_state' => 'Karnataka',
            'shipping_pincode' => '560038',
            'payment_method' => 'cod',
            ...$overrides,
        ];
    }

    /** Signs $user in, puts $quantity × $product in their cart and places a COD order. Returns the order JSON. */
    protected function placeCodOrder(User $user, Product $product, int $quantity = 1, array $overrides = []): array
    {
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])->assertOk();

        return $this->postJson('/api/v1/orders', $this->shipping($overrides))->assertCreated()->json('data');
    }
}
