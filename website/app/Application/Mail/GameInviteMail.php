<?php

namespace App\Application\Mail;

use App\Infrastructure\Models\Game;
use App\Infrastructure\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GameInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Player $inviter,
        public Game $game,
        public string $gameUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Du er invitert til et spill — SELECT',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.game-invite',
        );
    }
}
