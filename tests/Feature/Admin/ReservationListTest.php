<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Widgets\ReservationsOverview;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

/**
 * Clock: Monday 2026-10-12 10:00 Europe/Rome (week 12–18 October).
 */
class ReservationListTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        $this->actingAs(User::factory()->admin()->create());
        $this->table = $this->createTable([2, 4, 6], [], '08:00', '23:00');
    }

    private function reservationAt(string $date, string $time, int $partySize = 2): Reservation
    {
        return Reservation::factory()->on($this->table, $date, $time)->create(['party_size' => $partySize]);
    }

    public function test_list_opens_on_upcoming_confirmed_reservations_even_if_today_is_closed(): void
    {
        $yesterday = $this->reservationAt('2026-10-11', '20:00');
        $thursday = $this->reservationAt(self::THURSDAY, '20:00');
        $nextMonth = $this->reservationAt('2026-11-20', '20:00');
        $cancelled = Reservation::factory()->on($this->table, self::WEDNESDAY, '20:00')->cancelled()->create();

        Livewire::test(ListReservations::class)
            ->assertSet('activeTab', 'prossime')
            ->assertCanSeeTableRecords([$thursday, $nextMonth], inOrder: true)
            ->assertCanNotSeeTableRecords([$yesterday, $cancelled]);
    }

    public function test_reservation_in_progress_is_still_upcoming(): void
    {
        $inProgress = $this->reservationAt(self::TODAY, '09:30');
        $finished = $this->reservationAt(self::TODAY, '08:00');

        Livewire::test(ListReservations::class)
            ->assertCanSeeTableRecords([$inProgress])
            ->assertCanNotSeeTableRecords([$finished]);
    }

    public function test_period_tabs(): void
    {
        $finishedToday = $this->reservationAt(self::TODAY, '08:00');
        $tonight = $this->reservationAt(self::TODAY, '20:00');
        $tomorrow = $this->reservationAt('2026-10-13', '20:00');
        $sunday = $this->reservationAt('2026-10-18', '20:00');
        $nextMonday = $this->reservationAt('2026-10-19', '20:00');
        $lastDayOfMonth = $this->reservationAt('2026-10-31', '20:00');
        $november = $this->reservationAt('2026-11-02', '20:00');
        $lastWeek = $this->reservationAt('2026-10-05', '20:00');

        $all = [$finishedToday, $tonight, $tomorrow, $sunday, $nextMonday, $lastDayOfMonth, $november, $lastWeek];
        $expected = [
            'oggi' => [$finishedToday, $tonight],
            'domani' => [$tomorrow],
            'settimana' => [$finishedToday, $tonight, $tomorrow, $sunday],
            'mese' => [$lastWeek, $finishedToday, $tonight, $tomorrow, $sunday, $nextMonday, $lastDayOfMonth],
            'passate' => [$finishedToday, $lastWeek],
            'tutte' => $all,
        ];

        foreach ($expected as $tab => $visible) {
            Livewire::test(ListReservations::class)
                ->set('activeTab', $tab)
                ->assertCanSeeTableRecords($visible)
                ->assertCanNotSeeTableRecords(array_udiff($all, $visible, fn ($a, $b) => $a->id <=> $b->id));
        }

        Livewire::test(ListReservations::class)
            ->set('activeTab', 'passate')
            ->assertCanSeeTableRecords([$finishedToday, $lastWeek], inOrder: true);
    }

    public function test_status_filter_defaults_to_confirmed_but_can_show_cancelled(): void
    {
        $confirmed = $this->reservationAt(self::WEDNESDAY, '20:00');
        $cancelled = Reservation::factory()->on($this->table, self::THURSDAY, '20:00')->cancelled()->create();

        Livewire::test(ListReservations::class)
            ->assertCanNotSeeTableRecords([$cancelled])
            ->filterTable('status', ReservationStatus::Cancelled->value)
            ->assertCanSeeTableRecords([$cancelled])
            ->assertCanNotSeeTableRecords([$confirmed])
            ->removeTableFilter('status')
            ->assertCanSeeTableRecords([$confirmed, $cancelled]);
    }

    public function test_overview_counts_only_confirmed_reservations_and_covers(): void
    {
        $this->reservationAt(self::TODAY, '20:00', 4);
        $this->reservationAt(self::TODAY, '21:00', 2);
        Reservation::factory()->on($this->table, self::TODAY, '22:00')->cancelled()->create(['party_size' => 6]);
        $this->reservationAt('2026-10-13', '20:00', 6);
        $this->reservationAt('2026-10-17', '20:00', 2);
        $this->reservationAt('2026-10-28', '20:00', 2);

        Livewire::test(ReservationsOverview::class)
            ->assertSeeInOrder([
                'Prossimo servizio · lun 12 ott', '2', '6 coperti · dalle 20:00',
                'Oggi', '2', '6 coperti',
                'Domani', '1', '6 coperti',
                'Questa settimana', '4', '14 coperti',
                'Questo mese', '5', '16 coperti',
            ]);
    }

    public function test_next_service_points_to_the_next_open_day_when_today_is_closed(): void
    {
        $this->reservationAt(self::THURSDAY, '19:30', 4);
        $this->reservationAt(self::THURSDAY, '21:00', 2);

        Livewire::test(ReservationsOverview::class)
            ->assertSeeInOrder(['Prossimo servizio · gio 15 ott', '2', '6 coperti · dalle 19:30', 'Oggi', '0']);
    }

    public function test_overview_without_reservations(): void
    {
        Livewire::test(ReservationsOverview::class)
            ->assertSee('Nessuna prenotazione in programma');

        $this->get('/admin/prenotazioni')->assertOk()->assertSee('Prossime')->assertSee('Questa settimana');
    }
}
