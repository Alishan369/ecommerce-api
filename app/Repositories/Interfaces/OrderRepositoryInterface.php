<?php

namespace App\Repositories\Interfaces;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface OrderRepositoryInterface
{
    public function checkout(Request $request, array $data): Order;

    public function findForUser(string $orderNumber, User $user): Order;

    public function lookup(string $orderNumber, string $email): Order;

    public function getForUser(int $userId, int $perPage = 10): LengthAwarePaginator;

    public function getAdminOrders(array $filters = []): LengthAwarePaginator;

    public function updateStatus(Order $order, string $status): Order;

    public function cancelForCustomer(Order $order): Order;
}
