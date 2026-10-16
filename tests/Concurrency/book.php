<?php

/*
 * Child process used by ConcurrentBookingTest: boots the application on the MySQL
 * test database, waits for a shared start instant, then tries to create one public
 * reservation and prints the outcome as JSON.
 *
 * Usage: php book.php <unix start time with microseconds> <json payload>
 */

use App\Exceptions\BookingException;
use App\Services\Reservations\ReservationService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$startAt = (float) $argv[1];
$payload = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);

while (microtime(true) < $startAt) {
    usleep(100);
}

try {
    $reservation = app(ReservationService::class)->createPublic($payload)->reservation;

    echo json_encode(['created' => true, 'table_id' => $reservation->restaurant_table_id]);
} catch (BookingException $exception) {
    echo json_encode(['created' => false, 'error' => $exception->error->value]);
}
