<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {}

    public function index(Request $request)
    {
        $filters = $request->all();
        $products = $this->productRepository->getAllProducts($filters);

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

        return new ProductResource($product);
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
