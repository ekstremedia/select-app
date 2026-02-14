<?php

namespace Tests\Unit\Domain;

use App\Domain\Round\Services\AcronymGenerator;
use PHPUnit\Framework\TestCase;

class AcronymGeneratorTest extends TestCase
{
    private AcronymGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new AcronymGenerator;
    }

    public function test_generate_returns_string_of_correct_length(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $acronym = $this->generator->generate(3, 6);

            $this->assertGreaterThanOrEqual(3, strlen($acronym));
            $this->assertLessThanOrEqual(6, strlen($acronym));
        }
    }

    public function test_generate_returns_uppercase_letters(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $acronym = $this->generator->generate(4, 5);

            $this->assertMatchesRegularExpression('/^[A-Z]+$/', $acronym);
        }
    }

    public function test_generate_batch_returns_unique_acronyms(): void
    {
        $acronyms = $this->generator->generateBatch(10, 3, 5);

        $this->assertCount(10, $acronyms);
        $this->assertCount(10, array_unique($acronyms));
    }

    public function test_generate_with_min_equals_max_returns_exact_length(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $acronym = $this->generator->generate(4, 4);

            $this->assertEquals(4, strlen($acronym));
        }
    }
}
