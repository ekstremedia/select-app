<?php

namespace Tests\Feature\Mail;

use App\Application\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_mail_has_correct_subject(): void
    {
        $user = User::factory()->create();
        $mail = new PasswordResetMail($user, 'https://example.com/nytt-passord/token123');

        $mail->assertHasSubject('Tilbakestill passord — SELECT');
    }

    public function test_password_reset_mail_contains_reset_url(): void
    {
        $user = User::factory()->create();
        $url = 'https://example.com/nytt-passord/token123?email=test%40example.com';
        $mail = new PasswordResetMail($user, $url);

        $mail->assertSeeInHtml($url);
    }

    public function test_password_reset_mail_contains_user_nickname(): void
    {
        $user = User::factory()->create(['nickname' => 'TestNick']);
        $mail = new PasswordResetMail($user, 'https://example.com/reset');

        $mail->assertSeeInHtml('TestNick');
    }

    public function test_forgot_password_sends_custom_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'mailtest@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'mailtest@example.com',
        ]);

        Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_password_reset_url_uses_norwegian_route(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'route@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'route@example.com',
        ]);

        Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) {
            return str_contains($mail->url, '/nytt-passord/');
        });
    }
}
