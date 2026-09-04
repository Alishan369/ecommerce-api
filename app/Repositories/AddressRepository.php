<?php

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;

class AddressRepository implements AddressRepositoryInterface
{
    public function getUserAddresses(int $userId)
    {
        return Address::where('user_id', $userId)
            ->orderBy('is_default', 'desc')
            ->get();
    }

    public function findById(int $id)
    {
        return Address::findOrFail($id);
    }

    public function create(int $userId, array $data): Address
    {
        return Address::create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $address->update($data);
    }

    public function delete(int $id): bool
    {
        return $address->delete();
    }

    public function setDefault(int $userId, int $addressId)
    {
        Address::where('user_id', $userId)
            ->update(['is_default' => false]);

        return Address::where('id', $addressId)
            ->where('user_id', $userId)
            ->update(['is_default' => true]);
    }
}
