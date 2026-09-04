<?php

namespace App\Repositories\Interfaces;

interface AddressRepositoryInterface
{
    public function getUserAddresses(int $userId);

    public function findById(int $id);

    public function create(int $userId, array $data);

    public function update(int $id, array $data);

    public function delete(int $id);

    public function setDefault(int $userId, int $addressId);
}
