<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReservationRequest;
use App\Http\Resources\Api\V1\ReservationResource;
use App\Services\Reservations\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public const TOKEN_HEADER = 'X-Reservation-Token';

    public function __construct(private readonly ReservationService $reservations) {}

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $issued = $this->reservations->createPublic($request->validated());

        return ReservationResource::issued($issued)->response()->setStatusCode(201);
    }

    public function show(Request $request, string $reference): ReservationResource
    {
        return new ReservationResource(
            $this->reservations->findForCustomer($reference, $request->header(self::TOKEN_HEADER))
        );
    }

    public function cancel(Request $request, string $reference): ReservationResource
    {
        $reservation = $this->reservations->findForCustomer($reference, $request->header(self::TOKEN_HEADER));

        return new ReservationResource($this->reservations->cancel($reservation));
    }
}
