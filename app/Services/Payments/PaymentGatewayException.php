<?php

namespace App\Services\Payments;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The gateway couldn't be reached or refused the request. The message is customer-safe. */
class PaymentGatewayException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 503);
    }
}
