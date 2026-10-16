<?php

namespace App\Exceptions;

use App\Enums\BookingError;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A booking rule rejected the requested operation.
 */
class BookingException extends RuntimeException
{
    public function __construct(public readonly BookingError $error)
    {
        parent::__construct($error->message());
    }

    public static function because(BookingError $error): self
    {
        return new self($error);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->error->value,
                'message' => $this->error->message(),
            ],
        ], $this->error->httpStatus());
    }
}
