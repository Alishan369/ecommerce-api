<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Http\Request;

/** GET /v1/admin/customers — read-only list of registered customers with order totals. */
class CustomerController extends Controller
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return CustomerResource::collection(
            $this->users->paginateCustomers($filters['search'] ?? null, (int) ($filters['per_page'] ?? 20))
        );
    }
}
