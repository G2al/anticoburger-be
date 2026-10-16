<?php

namespace Tests\Feature\Reservations;

use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\Reservations\RestaurantTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class RestaurantTableServiceTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    private RestaurantTableService $tables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        $this->tables = app(RestaurantTableService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function tableData(array $overrides = []): array
    {
        return [
            'name' => 'Tavolo 6',
            'duration_minutes' => 30,
            'slot_interval_minutes' => 30,
            'turnaround_minutes' => 0,
            'assignment_priority' => 0,
            'is_active' => true,
            'party_sizes' => [2, 3, 4],
            'schedules' => [
                ['day_of_week' => 1, 'first_booking_time' => '12:00', 'last_booking_time' => '15:00'],
                ['day_of_week' => 1, 'first_booking_time' => '19:00', 'last_booking_time' => '23:00'],
                ['day_of_week' => 5, 'first_booking_time' => '19:00:00', 'last_booking_time' => '23:30:00'],
            ],
            ...$overrides,
        ];
    }

    private function assertInvalid(array $data, ?RestaurantTable $table = null): void
    {
        try {
            $table ? $this->tables->update($table, $data) : $this->tables->create($data);
            $this->fail('Validation should have failed');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_table_is_created_with_party_sizes_and_schedule(): void
    {
        $table = $this->tables->create($this->tableData());

        $this->assertSame([2, 3, 4], $table->load('allowedPartySizes')->partySizes());
        $this->assertSame(3, $table->schedules()->count());
        $this->assertDatabaseHas('table_schedules', [
            'restaurant_table_id' => $table->id,
            'day_of_week' => 1,
            'first_booking_time' => '19:00:00',
            'last_booking_time' => '23:00:00',
        ]);
    }

    public function test_table_is_updated_and_relations_are_synchronized(): void
    {
        $table = $this->tables->create($this->tableData());

        $this->tables->update($table, $this->tableData([
            'name' => 'Tavolo 6 bis',
            'duration_minutes' => 60,
            'turnaround_minutes' => 10,
            'party_sizes' => [2, 4, 6],
            'schedules' => [['day_of_week' => 3, 'first_booking_time' => '19:00', 'last_booking_time' => '22:00']],
        ]));

        $table->refresh()->load(['allowedPartySizes', 'schedules']);

        $this->assertSame('Tavolo 6 bis', $table->name);
        $this->assertSame(60, $table->duration_minutes);
        $this->assertSame(10, $table->turnaround_minutes);
        $this->assertSame([2, 4, 6], $table->partySizes());
        $this->assertSame([3], $table->schedules->pluck('day_of_week')->all());
    }

    public function test_table_can_be_deactivated_and_reactivated(): void
    {
        $table = $this->tables->create($this->tableData());

        $this->assertFalse($this->tables->setActive($table, false)->fresh()->is_active);
        $this->assertTrue($this->tables->setActive($table, true)->fresh()->is_active);
    }

    public function test_table_without_party_sizes_cannot_be_activated(): void
    {
        $table = $this->tables->create($this->tableData(['is_active' => false, 'party_sizes' => []]));

        $this->expectException(ValidationException::class);

        $this->tables->setActive($table, true);
    }

    public function test_invalid_table_data_is_rejected(): void
    {
        $this->tables->create($this->tableData());

        $this->assertInvalid($this->tableData());
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'party_sizes' => []]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'party_sizes' => [0]]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'party_sizes' => [2, 2]]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'duration_minutes' => 0]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'slot_interval_minutes' => 0]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'turnaround_minutes' => -5]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'schedules' => [
            ['day_of_week' => 8, 'first_booking_time' => '19:00', 'last_booking_time' => '22:00'],
        ]]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'schedules' => [
            ['day_of_week' => 1, 'first_booking_time' => '22:00', 'last_booking_time' => '19:00'],
        ]]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'schedules' => [
            ['day_of_week' => 1, 'first_booking_time' => '19:00', 'last_booking_time' => '22:00'],
            ['day_of_week' => 1, 'first_booking_time' => '21:00', 'last_booking_time' => '23:00'],
        ]]));
        $this->assertInvalid($this->tableData(['name' => 'Altro', 'schedules' => [
            ['day_of_week' => 1, 'first_booking_time' => '25:00', 'last_booking_time' => '26:00'],
        ]]));
    }

    public function test_inactive_table_without_party_sizes_can_be_saved(): void
    {
        $table = $this->tables->create($this->tableData(['is_active' => false, 'party_sizes' => [], 'schedules' => []]));

        $this->assertFalse($table->is_active);
    }

    public function test_table_with_history_cannot_be_deleted(): void
    {
        $table = $this->createTable([2]);
        Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->cancelled()->create();

        try {
            $this->tables->delete($table);
            $this->fail('Deletion should have failed');
        } catch (ValidationException) {
            $this->assertModelExists($table);
        }

        $unused = $this->createTable([2]);
        $this->tables->delete($unused);
        $this->assertModelMissing($unused);
        $this->assertDatabaseMissing('table_allowed_party_sizes', ['restaurant_table_id' => $unused->id]);
    }
}
