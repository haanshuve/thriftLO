<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_urls_stay_https_behind_a_proxy(): void
    {
        // Railway meneruskan request HTTPS ke app lewat HTTP dengan header X-Forwarded-Proto
        $html = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#src="https://[^"]+/images/logo\.jpeg\.jpeg"#', $html);
        $this->assertMatchesRegularExpression('#action="https://[^"]+/login"#', $html);
    }

    public function test_health_check_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_make_admin_command_promotes_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'admin@thriftlo.test', 'role' => 'pembeli']);

        $this->artisan('thriftlo:make-admin', ['email' => 'admin@thriftlo.test'])->assertExitCode(0);
        $this->assertSame('admin', $user->fresh()->role);

        $this->artisan('thriftlo:make-admin', ['email' => 'tidak-ada@thriftlo.test'])->assertExitCode(1);
    }

    public function test_env_example_lists_every_project_specific_variable(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        foreach (['APP_KEY', 'APP_URL', 'APP_TIMEZONE', 'DB_CONNECTION', 'SESSION_DRIVER=file', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI', 'SUBSCRIPTION_SIMULATION'] as $key) {
            $this->assertStringContainsString($key, $example, "{$key} belum ada di .env.example");
        }
    }
}
