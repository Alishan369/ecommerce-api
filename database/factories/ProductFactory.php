<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $price = fake()->numberBetween(500, 5000);
        $stock = fake()->numberBetween(1, 10000);
        $salePrice = fake()->numberBetween(300, $price - 100);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('??###??')),
            'category_id' => Category::inRandomOrder()->value('id'),
            'price' => $price,
            'sale_price' => $salePrice,
            'description' => fake()->sentence(15),
            'is_active' => true,
            'is_featured' => fake()->boolean(30),
            'stock' => $stock,
        ];
    }
}
