<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationChannel;
use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\Pages\CreateReservation;
use App\Filament\Resources\Reservations\Pages\EditReservation;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Models\AvailabilityBlock;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class ReservationResourceTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return [
            'date' => self::WEDNESDAY,
            'time' => '20:00',
            'party_size' => 2,
            'restaurant_table_id' => null,
            'first_name' => 'Giulia',
            'last_name' => 'Bianchi',
            'phone' => '06 1234567',
            'email' => null,
            'notes' => 'Telefonata',
            ...$overrides,
        ];
    }

    public function test_admin_creates_a_reservation_with_automatic_table(): void
    {
        $small = $this->createTable([2], ['name' => 'Piccolo']);
        $this->createTable([2, 4], ['name' => 'Grande']);

        Livewire::test(CreateReservation::class)
            ->fillForm($this->formData())
            ->call('create')
            ->assertHasNoFormErrors();

        $reservation = Reservation::query()->sole();
        $this->assertSame(ReservationChannel::Admin, $reservation->created_via);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame($small->id, $reservation->restaurant_table_id);
        $this->assertSame('+39061234567', $reservation->phone_normalized);
        $this->assertNull($reservation->cancellation_token_hash);
    }

    public function test_admin_can_pick_a_compatible_table(): void
    {
        $this->createTable([2], ['name' => 'Piccolo']);
        $large = $this->createTable([2, 4], ['name' => 'Grande']);

        Livewire::test(CreateReservation::class)
            ->fillForm($this->formData(['restaurant_table_id' => $large->id]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($large->id, Reservation::query()->sole()->restaurant_table_id);
    }

    public function test_conflicting_manual_reservation_is_rejected(): void
    {
        $table = $this->createTable([2]);
        Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        Livewire::test(CreateReservation::class)
            ->fillForm($this->formData(['time' => '20:30', 'restaurant_table_id' => $table->id]))
            ->call('create')
            ->assertNotified('Operazione non consentita');

        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_manual_reservation_respects_blocks_and_schedule(): void
    {
        $this->createTable([2], [], '19:00', '22:00');
        AvailabilityBlock::factory()->between(self::THURSDAY)->create();

        Livewire::test(CreateReservation::class)
            ->fillForm($this->formData(['date' => self::THURSDAY]))
            ->call('create')
            ->assertNotified('Operazione non consentita');

        Livewire::test(CreateReservation::class)
            ->fillForm($this->formData(['time' => '23:00']))
            ->call('create')
            ->assertNotified('Operazione non consentita');

        $this->assertSame(0, Reservation::query()->count());
    }

    public function test_editing_customer_data_does_not_touch_the_occupancy(): void
    {
        $table = $this->createTable([2], ['duration_minutes' => 60]);
        $reservation = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();
        $table->update(['duration_minutes' => 120]);
        AvailabilityBlock::factory()->between(self::WEDNESDAY)->create();

        Livewire::test(EditReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertSchemaStateSet(['date' => self::WEDNESDAY, 'time' => '20:00', 'restaurant_table_id' => $table->id])
            ->fillForm(['first_name' => 'Anna', 'notes' => 'Allergia noci'])
            ->call('save')
            ->assertHasNoFormErrors();

        $reservation->refresh();
        $this->assertSame('Anna', $reservation->first_name);
        $this->assertSame('Allergia noci', $reservation->notes);
        $this->assertSame('21:00', $reservation->localEndsAt()->format('H:i'));
        $this->assertSame($table->id, $reservation->restaurant_table_id);
    }

    public function test_editing_date_or_time_checks_availability_again(): void
    {
        $table = $this->createTable([2], ['duration_minutes' => 60]);
        $reservation = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();
        Reservation::factory()->on($table, self::WEDNESDAY, '22:00')->create();

        Livewire::test(EditReservation::class, ['record' => $reservation->getRouteKey()])
            ->fillForm(['time' => '21:30'])
            ->call('save')
            ->assertNotified('Operazione non consentita');

        $this->assertSame('20:00', $reservation->fresh()->localStartsAt()->format('H:i'));

        Livewire::test(EditReservation::class, ['record' => $reservation->getRouteKey()])
            ->fillForm(['time' => '20:30'])
            ->call('save')
            ->assertHasNoFormErrors();

        $reservation->refresh();
        $this->assertSame('20:30', $reservation->localStartsAt()->format('H:i'));
        $this->assertSame('21:30', $reservation->localEndsAt()->format('H:i'));
    }

    public function test_reservation_can_be_moved_to_another_free_compatible_table(): void
    {
        $first = $this->createTable([2], ['name' => 'Primo', 'duration_minutes' => 60]);
        $second = $this->createTable([2, 4], ['name' => 'Secondo', 'duration_minutes' => 90]);
        $busy = $this->createTable([2, 4, 6], ['name' => 'Occupato']);
        Reservation::factory()->on($busy, self::WEDNESDAY, '20:00')->create();
        $reservation = Reservation::factory()->on($first, self::WEDNESDAY, '20:00')->create();

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('moveToTable')->table($reservation), ['restaurant_table_id' => $busy->id])
            ->assertHasFormErrors(['restaurant_table_id']);

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('moveToTable')->table($reservation), ['restaurant_table_id' => $second->id])
            ->assertHasNoFormErrors()
            ->assertNotified('Prenotazione spostata');

        $reservation->refresh();
        $this->assertSame($second->id, $reservation->restaurant_table_id);
        $this->assertSame('21:30', $reservation->localEndsAt()->format('H:i'));
    }

    public function test_reservation_can_be_cancelled_from_the_admin(): void
    {
        $table = $this->createTable([2]);
        $reservation = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        Livewire::test(ViewReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('cancel')
            ->assertNotified('Prenotazione annullata');

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);

        Livewire::test(ViewReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertActionHidden('cancel')
            ->assertActionHidden('moveToTable');
    }

    public function test_cancelled_reservation_keeps_its_occupancy_when_customer_data_changes(): void
    {
        $table = $this->createTable([2]);
        $reservation = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->cancelled()->create();

        Livewire::test(EditReservation::class, ['record' => $reservation->getRouteKey()])
            ->fillForm(['last_name' => 'Verdi'])
            ->call('save')
            ->assertHasNoFormErrors();

        $reservation->refresh();
        $this->assertSame('Verdi', $reservation->last_name);
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
    }

    public function test_reservation_can_be_deleted_permanently_and_frees_the_table(): void
    {
        $table = $this->createTable([2]);
        $reservation = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        Livewire::test(ViewReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('Prenotazione eliminata definitivamente');

        $this->assertModelMissing($reservation);

        Livewire::test(CreateReservation::class)
            ->fillForm($this->formData(['restaurant_table_id' => $table->id]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_selected_reservations_can_be_deleted_in_bulk(): void
    {
        $table = $this->createTable([2]);
        $first = Reservation::factory()->on($table, self::WEDNESDAY, '19:00')->create();
        $second = Reservation::factory()->on($table, self::WEDNESDAY, '21:00')->cancelled()->create();
        $kept = Reservation::factory()->on($table, self::THURSDAY, '20:00')->create();

        Livewire::test(ListReservations::class)
            ->removeTableFilter('status')
            ->selectTableRecords([$first, $second])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelExists($kept);
    }

    public function test_non_admins_cannot_delete_reservations(): void
    {
        $table = $this->createTable([2]);
        $reservation = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        $this->assertFalse(User::factory()->create()->can('delete', $reservation));
        $this->assertTrue(auth()->user()->can('delete', $reservation));
    }

    public function test_list_filters_by_status_and_table(): void
    {
        $tableOne = $this->createTable([2], ['name' => 'Uno']);
        $tableTwo = $this->createTable([2], ['name' => 'Due']);
        $confirmed = Reservation::factory()->on($tableOne, self::WEDNESDAY, '20:00')->create();
        $cancelled = Reservation::factory()->on($tableTwo, self::THURSDAY, '20:00')->cancelled()->create();

        Livewire::test(ListReservations::class)
            ->removeTableFilter('status')
            ->assertCanSeeTableRecords([$confirmed, $cancelled])
            ->filterTable('status', ReservationStatus::Cancelled->value)
            ->assertCanSeeTableRecords([$cancelled])
            ->assertCanNotSeeTableRecords([$confirmed]);

        Livewire::test(ListReservations::class)
            ->removeTableFilter('status')
            ->filterTable('restaurant_table_id', $tableOne->id)
            ->assertCanSeeTableRecords([$confirmed])
            ->assertCanNotSeeTableRecords([$cancelled]);

        Livewire::test(ListReservations::class)
            ->removeTableFilter('status')
            ->filterTable('date', ['from' => self::THURSDAY, 'until' => self::THURSDAY])
            ->assertCanSeeTableRecords([$cancelled])
            ->assertCanNotSeeTableRecords([$confirmed]);

        Livewire::test(ListReservations::class)
            ->removeTableFilter('status')
            ->searchTable('+39')
            ->assertCanSeeTableRecords([$confirmed, $cancelled]);
    }
}
