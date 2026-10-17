<?php

namespace Tests\Concurrency;

use App\Models\BookingSettings;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Support\BookingTime;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

/**
 * Real concurrency test on MySQL/InnoDB (MariaDB): several PHP processes with their own
 * connections try to book at the same instant. Uses the dedicated test database
 * (DB_TEST_DATABASE, default "anticoburger_test"), never the development database.
 * Skipped when that database is not reachable.
 */
class ConcurrentBookingTest extends TestCase
{
    private const CONNECTION = 'mysql_concurrency';

    private const PROCESS_START_DELAY_SECONDS = 4.0;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) env('DB_TEST_DATABASE', 'anticoburger_test');

        if (! str_ends_with($this->database, '_test')) {
            $this->fail('The concurrency test database name must end with "_test".');
        }

        config([
            'database.connections.'.self::CONNECTION => [
                ...config('database.connections.mysql'),
                'database' => $this->database,
            ],
        ]);

        try {
            DB::connection(self::CONNECTION)->getPdo();
        } catch (Throwable $exception) {
            $this->markTestSkipped("MySQL test database [{$this->database}] not reachable: {$exception->getMessage()}");
        }

        config(['database.default' => self::CONNECTION]);
        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);

        BookingSettings::current()->update([
            'min_lead_minutes' => 0,
            'max_advance_days' => 0,
            'max_reservations_per_phone_per_day' => 2,
            'prevent_duplicate_bookings' => false,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge(self::CONNECTION);

        parent::tearDown();
    }

    private function createOpenTable(string $name, int $partySize = 2): RestaurantTable
    {
        return RestaurantTable::factory()
            ->withPartySizes([$partySize])
            ->withSchedule('00:00:00', '23:30:00')
            ->create(['name' => $name, 'duration_minutes' => 60, 'slot_interval_minutes' => 30]);
    }

    /**
     * Starts one process per payload; all of them try to book at the same instant.
     *
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array<string, mixed>>
     */
    private function bookConcurrently(array $payloads): array
    {
        $startAt = sprintf('%.6F', microtime(true) + self::PROCESS_START_DELAY_SECONDS);
        $connection = config('database.connections.mysql');

        $processes = array_map(function (array $payload) use ($startAt, $connection) {
            $process = new Process(
                [PHP_BINARY, __DIR__.'/book.php', $startAt, json_encode($payload)],
                base_path(),
                [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => 'mysql',
                    'DB_URL' => '',
                    'DB_HOST' => $connection['host'],
                    'DB_PORT' => (string) $connection['port'],
                    'DB_DATABASE' => $this->database,
                    'DB_USERNAME' => $connection['username'],
                    'DB_PASSWORD' => (string) $connection['password'],
                    'CACHE_STORE' => 'array',
                    'SESSION_DRIVER' => 'array',
                    'WAHA_URL' => '',
                    'WAHA_API_KEY' => '',
                    'VAPID_PUBLIC_KEY' => '',
                    'VAPID_PRIVATE_KEY' => '',
                ],
                timeout: 60,
            );
            $process->start();

            return $process;
        }, $payloads);

        return array_map(function (Process $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'Child process failed: '.$process->getErrorOutput().$process->getOutput());

            return json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'date' => BookingTime::today()->addDays(2)->toDateString(),
            'time' => '20:00',
            'party_size' => 2,
            'first_name' => 'Cliente',
            'last_name' => 'Concorrente',
            'phone' => '3330000000',
            'email' => null,
            'notes' => null,
            ...$overrides,
        ];
    }

    public function test_simultaneous_requests_for_the_last_table_produce_one_reservation(): void
    {
        $table = $this->createOpenTable('Ultimo tavolo');

        $results = $this->bookConcurrently(array_map(
            fn (int $index) => $this->payload(['phone' => sprintf('33300000%02d', $index)]),
            range(1, 8),
        ));

        $created = array_values(array_filter($results, fn (array $result) => $result['created']));
        $rejected = array_values(array_filter($results, fn (array $result) => ! $result['created']));

        $this->assertCount(1, $created);
        $this->assertCount(7, $rejected);
        $this->assertSame(['SLOT_NOT_AVAILABLE'], array_values(array_unique(array_column($rejected, 'error'))));
        $this->assertSame(1, Reservation::query()->where('restaurant_table_id', $table->id)->count());
    }

    public function test_simultaneous_requests_never_exceed_the_daily_phone_limit(): void
    {
        foreach (range(1, 6) as $index) {
            $this->createOpenTable("Tavolo {$index}");
        }

        $results = $this->bookConcurrently(array_map(
            fn (string $time) => $this->payload(['time' => $time]),
            ['19:00', '19:30', '20:00', '20:30', '21:00', '21:30'],
        ));

        $created = array_filter($results, fn (array $result) => $result['created']);
        $rejected = array_filter($results, fn (array $result) => ! $result['created']);

        $this->assertCount(2, $created);
        $this->assertSame(['PHONE_DAILY_LIMIT_REACHED'], array_values(array_unique(array_column($rejected, 'error'))));
        $this->assertSame(2, Reservation::query()->where('phone_normalized', '+393330000000')->count());
    }
}
