<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function create(array $data): User;

    public function findByEmail(string $email): ?User;

    /** Admin customer list with order totals. */
    public function paginateCustomers(?string $search, int $perPage): LengthAwarePaginator;

    /** Registered customers, optionally only those who joined on or after $since. */
    public function countCustomers(?CarbonInterface $since = null): int;
}
