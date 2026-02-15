<?php

namespace Tests\Unit\Domain;

use App\Domain\Round\Services\BotAnswerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotAnswerServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotAnswerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BotAnswerService;
    }

    public function test_generates_fallback_answer_matching_acronym(): void
    {
        $answer = $this->service->findAnswer('ABC');

        $words = preg_split('/\s+/', trim($answer));
        $this->assertCount(3, $words);
        $this->assertStringStartsWith('a', $words[0]);
        $this->assertStringStartsWith('b', $words[1]);
        $this->assertStringStartsWith('c', $words[2]);
    }

    public function test_generates_answer_for_single_letter(): void
    {
        $answer = $this->service->findAnswer('A');

        $words = preg_split('/\s+/', trim($answer));
        $this->assertCount(1, $words);
        $this->assertStringStartsWith('a', $words[0]);
    }

    public function test_generates_answer_for_long_acronym(): void
    {
        $answer = $this->service->findAnswer('ABCDEF');

        $words = preg_split('/\s+/', trim($answer));
        $this->assertCount(6, $words);
    }

    public function test_handles_lowercase_acronym(): void
    {
        $answer = $this->service->findAnswer('abc');

        $words = preg_split('/\s+/', trim($answer));
        $this->assertCount(3, $words);
        $this->assertStringStartsWith('A', strtoupper($words[0]));
    }

    public function test_handles_uncommon_letters(): void
    {
        $answer = $this->service->findAnswer('QXZ');

        $words = preg_split('/\s+/', trim($answer));
        $this->assertCount(3, $words);
    }
}
