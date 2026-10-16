<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\BookingSettingsPage;
use App\Filament\Resources\AvailabilityBlocks\Pages\CreateAvailabilityBlock;
use App\Filament\Resources\AvailabilityBlocks\Pages\ListAvailabilityBlocks;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\RestaurantTables\Pages\CreateRestaurantTable;
use App\Filament\Resources\RestaurantTables\Pages\EditRestaurantTable;
use App\Filament\Resources\RestaurantTables\Pages\ListRestaurantTables;
use App\Models\AvailabilityBlock;
use App\Models\BookingSettings;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class TablesBlocksAndSettingsAdminTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_creates_a_table_with_party_sizes_and_weekly_schedule(): void
    {
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateRestaurantTable::class)
            ->fillForm([
                'name' => 'Tavolo 6',
                'is_active' => true,
                'duration_minutes' => 30,
                'slot_interval_minutes' => 30,
                'turnaround_minutes' => 10,
                'assignment_priority' => 1,
                'party_sizes' => ['2', '3', '4'],
                'schedule_1' => [
                    ['first_booking_time' => '12:00', 'last_booking_time' => '15:00'],
                    ['first_booking_time' => '19:00', 'last_booking_time' => '23:00'],
                ],
                'schedule_5' => [
                    ['first_booking_time' => '19:00', 'last_booking_time' => '23:30'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $table = RestaurantTable::query()->with(['allowedPartySizes', 'schedules'])->sole();
        $this->assertSame([2, 3, 4], $table->partySizes());
        $this->assertSame(10, $table->turnaround_minutes);
        $this->assertSame([1, 1, 5], $table->schedules->sortBy(['day_of_week', 'first_booking_time'])->pluck('day_of_week')->values()->all());
    }

    public function test_overlapping_ranges_are_rejected_by_the_server(): void
    {
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateRestaurantTable::class)
            ->fillForm([
                'name' => 'Tavolo 7',
                'is_active' => true,
                'duration_minutes' => 60,
                'slot_interval_minutes' => 30,
                'turnaround_minutes' => 0,
                'assignment_priority' => 0,
                'party_sizes' => ['2'],
                'schedule_2' => [
                    ['first_booking_time' => '19:00', 'last_booking_time' => '22:00'],
                    ['first_booking_time' => '21:00', 'last_booking_time' => '23:00'],
                ],
            ])
            ->call('create')
            ->assertHasErrors();

        $undoRepeaterFake();

        $this->assertSame(0, RestaurantTable::query()->count());
    }

    public function test_admin_edits_a_table(): void
    {
        $table = $this->createTable([2, 4], ['name' => 'Tavolo 1'], '19:00', '22:00', days: [3]);
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(EditRestaurantTable::class, ['record' => $table->getRouteKey()])
            ->assertSchemaStateSet([
                'party_sizes' => ['2', '4'],
                'schedule_3' => [['first_booking_time' => '19:00', 'last_booking_time' => '22:00']],
            ])
            ->fillForm([
                'name' => 'Tavolo 1 terrazza',
                'party_sizes' => ['2', '4', '6'],
                'schedule_3' => [],
                'schedule_4' => [['first_booking_time' => '12:00', 'last_booking_time' => '14:00']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $table->refresh()->load(['allowedPartySizes', 'schedules']);
        $this->assertSame('Tavolo 1 terrazza', $table->name);
        $this->assertSame([2, 4, 6], $table->partySizes());
        $this->assertSame([4], $table->schedules->pluck('day_of_week')->all());
    }

    public function test_active_toggle_in_the_table_list(): void
    {
        $table = $this->createTable([2]);

        Livewire::test(ListRestaurantTables::class)
            ->call('updateTableColumnState', 'is_active', (string) $table->getKey(), false);

        $this->assertFalse($table->fresh()->is_active);

        $empty = RestaurantTable::factory()->inactive()->create();

        Livewire::test(ListRestaurantTables::class)
            ->call('updateTableColumnState', 'is_active', (string) $empty->getKey(), true)
            ->assertNotified('Operazione non consentita');

        $this->assertFalse($empty->fresh()->is_active);
    }

    public function test_non_admins_cannot_use_the_table_list_or_toggle(): void
    {
        $table = $this->createTable([2]);
        $this->actingAs(User::factory()->create());

        Livewire::test(ListRestaurantTables::class)->assertForbidden();

        $this->assertTrue($table->fresh()->is_active);
    }

    public function test_table_with_history_cannot_be_deleted_from_the_admin(): void
    {
        $table = $this->createTable([2]);
        Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();
        $unused = $this->createTable([2]);

        Livewire::test(ListRestaurantTables::class)
            ->assertActionHidden(TestAction::make('delete')->table($table))
            ->callAction(TestAction::make('delete')->table($unused));

        $this->assertModelExists($table);
        $this->assertModelMissing($unused);
    }

    public function test_block_creation_warns_about_existing_reservations_without_touching_them(): void
    {
        $table = $this->createTable([2]);
        $reservation = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        Livewire::test(CreateAvailabilityBlock::class)
            ->fillForm([
                'title' => 'Chiusura straordinaria',
                'scope' => 'restaurant',
                'starts_on' => self::WEDNESDAY,
                'ends_on' => self::THURSDAY,
                'is_all_day' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Prenotazioni già esistenti nel periodo bloccato');

        $block = AvailabilityBlock::query()->sole();
        $this->assertTrue($reservation->fresh()->isConfirmed());

        Livewire::test(ListReservations::class)
            ->filterTable('block', ['availability_block_id' => $block->id])
            ->assertCanSeeTableRecords([$reservation]);
    }

    public function test_table_block_requires_a_table(): void
    {
        Livewire::test(CreateAvailabilityBlock::class)
            ->fillForm([
                'title' => 'Tavolo rotto',
                'scope' => 'table',
                'restaurant_table_id' => null,
                'starts_on' => self::WEDNESDAY,
                'ends_on' => self::WEDNESDAY,
                'is_all_day' => false,
                'start_time' => '19:00',
                'end_time' => '21:00',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['restaurant_table_id']);

        $this->assertSame(0, AvailabilityBlock::query()->count());
    }

    public function test_block_can_be_deactivated_and_deleted(): void
    {
        $block = AvailabilityBlock::factory()->between(self::WEDNESDAY)->create();

        Livewire::test(ListAvailabilityBlocks::class)
            ->call('updateTableColumnState', 'is_active', (string) $block->getKey(), false);

        $this->assertFalse($block->fresh()->is_active);

        Livewire::test(ListAvailabilityBlocks::class)
            ->callAction(TestAction::make('delete')->table($block));

        $this->assertModelMissing($block);
    }

    public function test_settings_page_updates_the_singleton(): void
    {
        Livewire::test(BookingSettingsPage::class)
            ->assertSchemaStateSet([
                'min_lead_minutes' => 30,
                'max_advance_days' => 30,
                'max_reservations_per_phone_per_day' => 2,
                'prevent_duplicate_bookings' => true,
            ])
            ->fillForm([
                'min_lead_minutes' => 0,
                'max_advance_days' => 60,
                'max_reservations_per_phone_per_day' => 0,
                'prevent_duplicate_bookings' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Impostazioni salvate');

        $settings = BookingSettings::current();
        $this->assertSame(1, BookingSettings::query()->count());
        $this->assertSame(0, $settings->min_lead_minutes);
        $this->assertSame(60, $settings->max_advance_days);
        $this->assertSame(0, $settings->max_reservations_per_phone_per_day);
        $this->assertFalse($settings->prevent_duplicate_bookings);

        Livewire::test(BookingSettingsPage::class)
            ->fillForm(['min_lead_minutes' => -1])
            ->call('save')
            ->assertHasFormErrors(['min_lead_minutes']);
    }
}
