<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryFiltersRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {}

    public function index(CategoryFiltersRequest $request): JsonResponse
    {
        $categories = $this->categoryRepository->getAllCategories($request->validated());

        return CategoryResource::collection($categories)->response();
    }

    /**
     * Nested active tree for public nav / mega-menu.
     * GET /v1/categories/tree
     */
    public function tree(): JsonResponse
    {
        $tree = $this->categoryRepository->getTree();

        return CategoryResource::collection($tree)->response();
    }

    /**
     * Full tree including inactive categories, for the admin panel.
     * GET /v1/admin/categories/tree
     */
    public function adminTree(): JsonResponse
    {
        $tree = $this->categoryRepository->getTree(includeInactive: true);

        return CategoryResource::collection($tree)->response();
    }

    public function show(string $slug): JsonResponse
    {
        $category = $this->categoryRepository->findActiveBySlug($slug);

        return (new CategoryResource($category))->response();
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryRepository->store($request->validated());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, string $slug): JsonResponse
    {
        $category = $this->categoryRepository->update($slug, $request->validated());

        return (new CategoryResource($category))->response();
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $onChildren = $request->query('on_children', 'reject');

        $this->categoryRepository->delete($slug, $onChildren);

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully.',
        ]);
    }
}
