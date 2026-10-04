<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductFiltersRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {}

    public function index(ProductFiltersRequest $request)
    {
        $products = $this->productRepository->getAllProducts($request->validated());

        return ProductResource::collection($products);
    }

    public function adminIndex(ProductFiltersRequest $request)
    {
        $products = $this->productRepository->getAdminProducts($request->validated());

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        $product = $this->productRepository->findBySlug($slug);

        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productRepository->store($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function update(Product $product, UpdateProductRequest $request)
    {
        $product = $this->productRepository->update($product, $request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $this->productRepository->delete($product);

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted successfully.',
        ]);
    }
}
