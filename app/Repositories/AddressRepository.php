<?php

namespace App\Repositories;

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddressRepository implements AddressRepositoryInterface
{
    private const MAX_PER_USER = 10;

    public function forUser(int $userId): Collection
    {
        return Address::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->latest('updated_at')
            ->get();
    }

    /** Scoped by user — another customer's address id is simply "not found". */
    public function findForUser(int $userId, int $addressId): Address
    {
        return Address::query()->where('user_id', $userId)->findOrFail($addressId);
    }

    public function create(int $userId, array $data): Address
    {
        $count = Address::query()->where('user_id', $userId)->count();

        if ($count >= self::MAX_PER_USER) {
            throw ValidationException::withMessages([
                'address' => 'You can save up to '.self::MAX_PER_USER.' addresses. Remove one to add another.',
            ]);
        }

        return DB::transaction(function () use ($userId, $data, $count) {
            // The first address is always the default.
            $isDefault = $count === 0 || ! empty($data['is_default']);

            if ($isDefault) {
                $this->clearDefault($userId);
            }

            return Address::create([...$data, 'user_id' => $userId, 'is_default' => $isDefault]);
        });
    }

    public function update(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data) {
            if (! empty($data['is_default'])) {
                $this->clearDefault($address->user_id);
            } else {
                unset($data['is_default']); // can't "un-default" directly — pick another default instead
            }

            $address->update($data);

            return $address->refresh();
        });
    }

    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                Address::query()
                    ->where('user_id', $address->user_id)
                    ->latest('updated_at')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }

    private function clearDefault(int $userId): void
    {
        Address::query()->where('user_id', $userId)->where('is_default', true)->update(['is_default' => false]);
    }
}
