<?php

namespace App\Repositories\Interfaces;

use App\Models\Address;
use Illuminate\Support\Collection;

interface AddressRepositoryInterface
{
    public function forUser(int $userId): Collection;

    public function findForUser(int $userId, int $addressId): Address;

    public function create(int $userId, array $data): Address;

    public function update(Address $address, array $data): Address;

    public function delete(Address $address): void;
}
