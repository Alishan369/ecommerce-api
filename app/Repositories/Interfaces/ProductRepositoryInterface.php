<?php

namespace App\Repositories\Interfaces;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function getAllProducts(array $filters = []): LengthAwarePaginator;

    public function store(array $data): Product;

    public function update(Product $product, array $data): Product;

    public function delete(Product $product): bool;

    public function findBySlug(string $slug): Product;
}
