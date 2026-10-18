<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Http\Resources\AdminCouponResource;
use App\Models\Coupon;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admin coupon management with usage stats (uses, discount given, revenue) from non-cancelled orders. */
class CouponController extends Controller
{
    public function __construct(private readonly CouponRepositoryInterface $coupons) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:40'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return AdminCouponResource::collection(
            $this->coupons->paginateWithStats($filters['search'] ?? null, (int) ($filters['per_page'] ?? 20))
        );
    }

    public function store(CouponRequest $request): JsonResponse
    {
        return (new AdminCouponResource($this->coupons->store($request->validated())))->response()->setStatusCode(201);
    }

    public function update(CouponRequest $request, Coupon $coupon): AdminCouponResource
    {
        return new AdminCouponResource($this->coupons->update($coupon, $request->validated()));
    }

    /** Used coupons are kept for order history and reporting — switch them off instead. */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $orders = $this->coupons->orderCount($coupon);

        if ($orders > 0) {
            throw ValidationException::withMessages([
                'coupon' => "{$coupon->code} has been used on {$orders} order(s). Deactivate it instead so your reports stay complete.",
            ]);
        }

        $this->coupons->delete($coupon);

        return response()->json(['status' => 'success', 'message' => 'Coupon deleted.']);
    }
}
