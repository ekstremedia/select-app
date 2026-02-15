<?php

namespace App\Domain\Round\Services;

class AcronymValidator
{
    public function validate(string $answer, string $acronym): ValidationResult
    {
        $answer = trim($answer);
        $acronym = strtoupper($acronym);

        if (empty($answer)) {
            return new ValidationResult(false, 'Answer cannot be empty');
        }

        // Only letters, spaces, and basic punctuation allowed
        if (preg_match('/[^\p{L}\s,.\!\?:;\-]/u', $answer)) {
            return new ValidationResult(false, 'Only letters and punctuation (,.!?:;-) are allowed');
        }

        // Split answer into words (handle multiple spaces)
        $words = preg_split('/\s+/', $answer);
        $words = array_filter($words, fn ($word) => ! empty($word));
        $words = array_values($words);

        $acronymLetters = str_split($acronym);

        if (count($words) !== count($acronymLetters)) {
            return new ValidationResult(
                false,
                sprintf(
                    'Expected %d words but got %d',
                    count($acronymLetters),
                    count($words)
                )
            );
        }

        foreach ($words as $index => $word) {
            $expectedLetter = $acronymLetters[$index];
            $actualLetter = strtoupper(mb_substr($word, 0, 1));

            if ($actualLetter !== $expectedLetter) {
                return new ValidationResult(
                    false,
                    sprintf(
                        'Word %d should start with "%s" but starts with "%s"',
                        $index + 1,
                        $expectedLetter,
                        $actualLetter
                    )
                );
            }
        }

        return new ValidationResult(true);
    }
}

class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly ?string $error = null
    ) {}
}
