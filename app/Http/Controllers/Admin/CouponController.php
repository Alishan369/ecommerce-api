<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Http\Resources\AdminCouponResource;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admin coupon management with usage stats (uses, discount given, revenue) from non-cancelled orders. */
class CouponController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:40'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $search = $filters['search'] ?? null;

        $coupons = $this->withStats(Coupon::query())
            ->when($search, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('code', 'like', '%'.addcslashes(strtoupper($search), '%_\\').'%')
                ->orWhere('description', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest()
            ->orderByDesc('id')
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100))
            ->withQueryString();

        return AdminCouponResource::collection($coupons);
    }

    public function store(CouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated());

        return (new AdminCouponResource($this->fresh($coupon)))->response()->setStatusCode(201);
    }

    public function update(CouponRequest $request, Coupon $coupon): AdminCouponResource
    {
        $coupon->update($request->validated());

        return new AdminCouponResource($this->fresh($coupon));
    }

    /** Used coupons are kept for order history and reporting — switch them off instead. */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $orders = $coupon->orders()->count();

        if ($orders > 0) {
            throw ValidationException::withMessages([
                'coupon' => "{$coupon->code} has been used on {$orders} order(s). Deactivate it instead so your reports stay complete.",
            ]);
        }

        $coupon->delete();

        return response()->json(['status' => 'success', 'message' => 'Coupon deleted.']);
    }

    private function withStats(Builder $query): Builder
    {
        return $query
            ->withCount('redemptions as uses')
            ->withSum('redemptions as discount_given', 'discount_amount')
            ->withSum('redemptions as revenue', 'total');
    }

    private function fresh(Coupon $coupon): Coupon
    {
        return $this->withStats(Coupon::query())->findOrFail($coupon->id);
    }
}
