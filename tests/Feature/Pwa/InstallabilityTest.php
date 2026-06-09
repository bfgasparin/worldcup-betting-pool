<?php

namespace Tests\Feature\Pwa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_advertises_the_pwa_manifest_and_meta_tags(): void
    {
        // The home route is guest-accessible; stub Vite so the Inertia root view renders. The PWA
        // <head> tags sit before @vite, so they render regardless of the asset pipeline.
        $this->withoutVite()
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('name="theme-color" content="#0fa968"', false)
            ->assertSee('name="apple-mobile-web-app-title" content="Brothers Bets"', false);
    }

    public function test_the_manifest_file_exists_and_is_valid(): void
    {
        $path = public_path('manifest.webmanifest');

        $this->assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true);

        $this->assertSame('Brothers Bets', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
    }
}
