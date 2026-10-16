<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\BookingStatsOverview;
use App\Filament\Widgets\UpcomingReservations;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeBookingClock();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_widgets_work_without_any_data(): void
    {
        Livewire::test(BookingStatsOverview::class)
            ->assertOk()
            ->assertSee('Confermate oggi')
            ->assertSee('Tavoli attivi');

        Livewire::test(UpcomingReservations::class)
            ->assertOk()
            ->assertSee('Nessuna prenotazione in arrivo');
    }

    public function test_widgets_show_today_and_upcoming_reservations(): void
    {
        $table = $this->createTable([2], [], '09:00', '23:00');
        $this->createTable([2], ['is_active' => false]);
        $todayConfirmed = Reservation::factory()->on($table, self::TODAY, '20:00')->create();
        Reservation::factory()->on($table, self::TODAY, '21:00')->cancelled()->create();
        $past = Reservation::factory()->on($table, self::TODAY, '09:00')->create();
        $future = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();

        Livewire::test(BookingStatsOverview::class)
            ->assertSeeInOrder(['Confermate oggi', '2', 'Annullate oggi', '1', 'Confermate future', '2', 'Tavoli attivi', '1']);

        Livewire::test(UpcomingReservations::class)
            ->assertCanSeeTableRecords([$todayConfirmed, $future], inOrder: true)
            ->assertCanNotSeeTableRecords([$past]);
    }
}
