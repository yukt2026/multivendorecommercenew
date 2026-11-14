<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRoutesAuditTest extends TestCase
{
    use RefreshDatabase;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::first() ?? User::factory()->create(['status' => 'active']);
        if (!$this->admin->hasRole('super_admin')) {
            $this->admin->assignRole('super_admin');
        }
    }

    public function test_all_admin_get_routes_render_cleanly_without_exception(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), 'admin'))
            ->filter(fn ($r) => !str_contains($r->uri(), '{'))
            ->filter(fn ($r) => !in_array($r->uri(), ['admin/login', 'admin/logout'], true))
            ->values();

        $errors = [];

        foreach ($routes as $route) {
            $uri = '/' . $route->uri();

            try {
                $response = $this->actingAs($this->admin)->get($uri);
                $status = $response->getStatusCode();

                if ($status !== 200 && $status !== 302) {
                    $errors[] = "Route {$uri} returned HTTP {$status}";
                }

                // Check for double escaped ampersands or raw &rarr; in HTML responses
                if (!($response->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse)) {
                    $content = $response->getContent();
                    if (str_contains($content, '&amp;amp;')) {
                        $errors[] = "Route {$uri} has double-escaped &amp;amp;";
                    }
                    if (str_contains($content, '&amp;rarr;')) {
                        $errors[] = "Route {$uri} has double-escaped &amp;rarr;";
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Route {$uri} threw exception: {$e->getMessage()}";
            }
        }

        $this->assertEmpty($errors, "Admin route audit failed:\n" . implode("\n", $errors));
    }
}
