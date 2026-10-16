<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_exposes_pwa_tags_and_both_theme_logos(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('rel="manifest"', escape: false)
            ->assertSee('manifest.webmanifest')
            ->assertSee('apple-touch-icon.png')
            ->assertSee('apple-mobile-web-app-capable', escape: false)
            ->assertSee('favicon.ico')
            ->assertSee('navigator.serviceWorker.register', escape: false)
            ->assertSee('images/logo-anticoburger-dark.png')
            ->assertSee('images/logo-anticoburger-white.png');
    }

    public function test_panel_pages_include_the_pwa_tags(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('manifest.webmanifest')
            ->assertSee('images/logo-anticoburger-dark.png')
            ->assertSee('images/logo-anticoburger-white.png');
    }

    public function test_manifest_is_installable_and_references_existing_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/admin', $manifest['start_url']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);

        $sizes = [];

        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path);

            [$width, $height] = getimagesize($path);
            $this->assertSame($icon['sizes'], "{$width}x{$height}");
            $sizes[] = $icon['sizes'].'-'.$icon['purpose'];
        }

        $this->assertContains('192x192-any', $sizes);
        $this->assertContains('512x512-any', $sizes);
        $this->assertContains('512x512-maskable', $sizes);

        foreach (['sw.js', 'offline.html', 'favicon.ico', 'icons/apple-touch-icon.png', 'icons/favicon-32.png'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }
}
