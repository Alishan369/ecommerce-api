<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryFiltersRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Repositories\Interfaces\CategoryRepositoryInterface;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {}

    public function index(CategoryFiltersRequest $request)
    {
        $categories = $this->categoryRepository->getAllCategories($request->validated());

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {

        try {
            $category = $this->categoryRepository->store($request->validated());

            return new CategoryResource($category);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function update(UpdateCategoryRequest $request, $id)
    {
        try {
            $category = $this->categoryRepository->update($id, $request->validated());

            return new CategoryResource($category);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function destroy($id)
    {
        try {
            $category = $this->categoryRepository->delete($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully.',
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
