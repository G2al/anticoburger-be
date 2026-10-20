<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TableSchedule;
use App\Support\BookingTime;
use Illuminate\Http\JsonResponse;

class BookingHoursController extends Controller
{
    /** Weekly arrival windows, independent of reservations and date-specific closures. */
    public function __invoke(): JsonResponse
    {
        $schedules = TableSchedule::query()
            ->whereHas('restaurantTable', fn ($query) => $query->active()->whereHas('allowedPartySizes'))
            ->orderBy('first_booking_time')
            ->orderBy('last_booking_time')
            ->get()
            ->groupBy('day_of_week');

        $days = [];
        foreach (range(1, 7) as $day) {
            $ranges = [];
            foreach ($schedules->get($day, collect()) as $schedule) {
                $first = substr($schedule->first_booking_time, 0, 5);
                $last = substr($schedule->last_booking_time, 0, 5);
                $index = count($ranges) - 1;
                // Merge overlapping windows, preserving gaps between separate services.
                if ($index >= 0 && $first <= $ranges[$index]['last_booking_time']) {
                    $ranges[$index]['last_booking_time'] = max($ranges[$index]['last_booking_time'], $last);
                } else {
                    $ranges[] = ['first_booking_time' => $first, 'last_booking_time' => $last];
                }
            }
            $days[] = ['day_of_week' => $day, 'ranges' => $ranges];
        }

        return response()->json(['data' => ['timezone' => BookingTime::timezone(), 'days' => $days]])
            ->header('Cache-Control', 'no-store');
    }
}
