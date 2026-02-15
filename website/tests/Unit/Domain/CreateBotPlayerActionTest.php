<?php

namespace Tests\Unit\Domain;

use App\Domain\Player\Actions\CreateBotPlayerAction;
use App\Infrastructure\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateBotPlayerActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_bot_player_with_default_name(): void
    {
        $action = new CreateBotPlayerAction;
        $player = $action->execute();

        $this->assertInstanceOf(Player::class, $player);
        $this->assertTrue($player->is_bot);
        $this->assertTrue($player->is_guest);
        $this->assertNotEmpty($player->nickname);
        $this->assertNotEmpty($player->guest_token);
    }

    public function test_creates_bot_player_with_custom_name(): void
    {
        $action = new CreateBotPlayerAction;
        $player = $action->execute('CustomBot');

        $this->assertEquals('CustomBot', $player->nickname);
        $this->assertTrue($player->is_bot);
    }

    public function test_bot_name_follows_expected_pattern(): void
    {
        $action = new CreateBotPlayerAction;
        $player = $action->execute();

        // Name should end with 2-digit number
        $this->assertMatchesRegularExpression('/^[A-Za-z]+\d{2}$/', $player->nickname);
    }

    public function test_creates_multiple_unique_bots(): void
    {
        $action = new CreateBotPlayerAction;
        $names = [];

        for ($i = 0; $i < 10; $i++) {
            $player = $action->execute();
            $names[] = $player->nickname;
        }

        // All players should be saved in DB
        $this->assertEquals(10, Player::where('is_bot', true)->count());
    }
}
