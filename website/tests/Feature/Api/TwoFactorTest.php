<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('api')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_can_enable_two_factor(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/two-factor/enable');

        $response->assertStatus(200)
            ->assertJsonStructure(['secret', 'qr_code_url', 'recovery_codes']);

        $this->assertCount(8, $response->json('recovery_codes'));
    }

    public function test_cannot_enable_two_factor_when_already_enabled(): void
    {
        $google2fa = new Google2FA;
        $this->user->update([
            'two_factor_secret' => $google2fa->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/two-factor/enable');

        $response->assertStatus(422)
            ->assertJson(['error' => 'Two-factor authentication is already enabled.']);
    }

    public function test_can_confirm_two_factor_with_valid_code(): void
    {
        // Enable first
        $enableResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/two-factor/enable');

        $secret = $enableResponse->json('secret');

        // Generate valid TOTP code
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/two-factor/confirm', [
                'code' => $code,
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Two-factor authentication confirmed.']);

        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_confirmed_at);
    }

    public function test_cannot_confirm_with_invalid_code(): void
    {
        // Enable first
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/two-factor/enable');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/two-factor/confirm', [
                'code' => '000000',
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'The provided code is invalid.']);
    }

    public function test_cannot_confirm_without_enabling_first(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/two-factor/confirm', [
                'code' => '123456',
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Two-factor authentication has not been enabled.']);
    }

    public function test_can_disable_two_factor_with_password(): void
    {
        $google2fa = new Google2FA;
        $this->user->update([
            'two_factor_secret' => $google2fa->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode(['code1', 'code2']),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/two-factor/disable', [
                'password' => 'password',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Two-factor authentication disabled.']);

        $this->user->refresh();
        $this->assertNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_confirmed_at);
    }

    public function test_cannot_disable_with_wrong_password(): void
    {
        $google2fa = new Google2FA;
        $this->user->update([
            'two_factor_secret' => $google2fa->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/two-factor/disable', [
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'The provided password is incorrect.']);
    }

    public function test_two_factor_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/two-factor/enable');

        $response->assertStatus(401);
    }
}
