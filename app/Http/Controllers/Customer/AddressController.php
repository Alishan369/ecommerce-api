<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The signed-in customer's address book (used to prefill checkout). */
class AddressController extends Controller
{
    public function __construct(private readonly AddressRepositoryInterface $addresses) {}

    public function index(Request $request)
    {
        return AddressResource::collection($this->addresses->forUser($request->user()->id));
    }

    public function store(StoreAddressRequest $request)
    {
        $address = $this->addresses->create($request->user()->id, $request->validated());

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function update(UpdateAddressRequest $request, int $address): AddressResource
    {
        $model = $this->addresses->findForUser($request->user()->id, $address);

        return new AddressResource($this->addresses->update($model, $request->validated()));
    }

    public function destroy(Request $request, int $address): Response
    {
        $this->addresses->delete($this->addresses->findForUser($request->user()->id, $address));

        return response()->noContent();
    }
}
