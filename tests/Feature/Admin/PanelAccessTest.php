<?php

namespace Tests\Feature\Admin;

use App\Models\AvailabilityBlock;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookingScenarios;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use BuildsBookingScenarios;
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_non_admin_users_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/prenotazioni')->assertForbidden();
        $this->actingAs($user)->get('/admin/impostazioni-prenotazioni')->assertForbidden();
    }

    public function test_admin_can_open_every_page_even_without_data(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            '/admin',
            '/admin/prenotazioni',
            '/admin/prenotazioni/create',
            '/admin/tavoli',
            '/admin/tavoli/create',
            '/admin/blocchi-disponibilita',
            '/admin/blocchi-disponibilita/create',
            '/admin/impostazioni-prenotazioni',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_open_record_pages(): void
    {
        $this->freezeBookingClock();
        $admin = User::factory()->admin()->create();
        $table = $this->createTable([2]);
        $reservation = Reservation::factory()->on($table, self::WEDNESDAY, '20:00')->create();
        $block = AvailabilityBlock::factory()->between(self::WEDNESDAY)->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get("/admin/prenotazioni/{$reservation->id}")->assertOk()->assertSee($reservation->first_name);
        $this->actingAs($admin)->get("/admin/prenotazioni/{$reservation->id}/edit")->assertOk();
        $this->actingAs($admin)->get("/admin/tavoli/{$table->id}/edit")->assertOk();
        $this->actingAs($admin)->get("/admin/blocchi-disponibilita/{$block->id}/edit")->assertOk()->assertSee('Prenotazioni coinvolte');
    }

    public function test_admin_role_can_be_granted_only_from_the_console(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);

        $this->assertFalse($user->fresh()->isAdmin());

        $this->artisan('app:grant-admin', ['email' => 'staff@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());

        $this->artisan('app:grant-admin', ['email' => 'staff@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->isAdmin());

        $this->artisan('app:grant-admin', ['email' => 'missing@example.com'])->assertFailed();

        $this->assertFalse((new User(['name' => 'x', 'email' => 'x@example.com', 'password' => 'secret', 'is_admin' => true]))->isAdmin());
    }

    public function test_sidebar_is_organized_in_groups(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder([
                'Dashboard',
                'Prenotazioni', 'Prenotazioni', 'Blocchi disponibilità', 'Impostazioni',
                'Sala', 'Tavoli',
                'Menu', 'Prodotti', 'Categorie',
            ]);
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/admin/register')->assertNotFound();
    }
}
