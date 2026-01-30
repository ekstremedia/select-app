<?php

namespace Tests\Unit\Domain;

use App\Domain\Round\Services\AcronymValidator;
use PHPUnit\Framework\TestCase;

class AcronymValidatorTest extends TestCase
{
    private AcronymValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AcronymValidator();
    }

    public function test_validates_correct_answer(): void
    {
        $result = $this->validator->validate('This Is How We Play', 'TIHWP');

        $this->assertTrue($result->isValid);
        $this->assertNull($result->error);
    }

    public function test_validates_lowercase_answer(): void
    {
        $result = $this->validator->validate('this is how we play', 'TIHWP');

        $this->assertTrue($result->isValid);
    }

    public function test_rejects_empty_answer(): void
    {
        $result = $this->validator->validate('', 'ABC');

        $this->assertFalse($result->isValid);
        $this->assertEquals('Answer cannot be empty', $result->error);
    }

    public function test_rejects_wrong_word_count(): void
    {
        $result = $this->validator->validate('This Is How', 'TIHWP');

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Expected 5 words but got 3', $result->error);
    }

    public function test_rejects_wrong_starting_letter(): void
    {
        $result = $this->validator->validate('That Is How We Play', 'XIHWP');

        $this->assertFalse($result->isValid);
        $this->assertStringContainsString('Word 1 should start with "X"', $result->error);
    }

    public function test_handles_multiple_spaces(): void
    {
        $result = $this->validator->validate('This   Is   How', 'TIH');

        $this->assertTrue($result->isValid);
    }

    public function test_handles_leading_trailing_spaces(): void
    {
        $result = $this->validator->validate('  This Is How  ', 'TIH');

        $this->assertTrue($result->isValid);
    }

    public function test_validates_single_word(): void
    {
        $result = $this->validator->validate('Test', 'T');

        $this->assertTrue($result->isValid);
    }
}
