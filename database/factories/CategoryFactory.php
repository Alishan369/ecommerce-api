<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'image' => 'https://bellavitaorganic.com/cdn/shop/files/Offer-Mobile-_1_UPB-mobile.webp?v=1727436765&width=800',
            'is_active' => true,
        ];
    }
}
