<?php

namespace Tests\Feature\Mail;

use App\Application\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_mail_has_correct_subject(): void
    {
        $user = User::factory()->create();
        $mail = new WelcomeMail($user);

        $mail->assertHasSubject('Velkommen til SELECT!');
    }

    public function test_welcome_mail_contains_user_nickname(): void
    {
        $user = User::factory()->create(['nickname' => 'WelcomeNick']);
        $mail = new WelcomeMail($user);

        $mail->assertSeeInHtml('WelcomeNick');
    }

    public function test_registration_sends_welcome_mail(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', [
            'email' => 'welcome@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nickname' => 'WelcomeUser',
        ]);

        Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail) {
            return $mail->hasTo('welcome@example.com');
        });
    }

    public function test_guest_conversion_sends_welcome_mail(): void
    {
        Mail::fake();

        $guestResponse = $this->postJson('/api/v1/auth/guest', [
            'nickname' => 'ConvertGuest',
        ]);
        $guestToken = $guestResponse->json('player.guest_token');

        $this->postJson('/api/v1/auth/register', [
            'guest_token' => $guestToken,
            'email' => 'converted@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail) {
            return $mail->hasTo('converted@example.com');
        });
    }
}
