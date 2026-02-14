<?php

namespace App\Domain\Round\Services;

class AcronymGenerator
{
    // Consonants that commonly start words in English/Norwegian
    private const COMMON_CONSONANTS = ['B', 'C', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V', 'W'];

    // Vowels
    private const VOWELS = ['A', 'E', 'I', 'O', 'U'];

    // Less common but usable letters
    private const RARE_LETTERS = ['Q', 'X', 'Y', 'Z'];

    // Letter frequencies (higher = more likely)
    private const LETTER_WEIGHTS = [
        'A' => 8, 'B' => 5, 'C' => 4, 'D' => 5, 'E' => 8,
        'F' => 4, 'G' => 4, 'H' => 5, 'I' => 7, 'J' => 2,
        'K' => 3, 'L' => 5, 'M' => 5, 'N' => 5, 'O' => 6,
        'P' => 5, 'Q' => 1, 'R' => 6, 'S' => 7, 'T' => 7,
        'U' => 4, 'V' => 3, 'W' => 3, 'X' => 1, 'Y' => 2,
        'Z' => 1,
    ];

    private array $excludedLetters = [];

    public function setExcludedLetters(string $excluded): self
    {
        $this->excludedLetters = array_map('strtoupper', str_split(preg_replace('/[^a-zA-Z]/', '', $excluded)));

        return $this;
    }

    public function generate(int $minLength = 3, int $maxLength = 6): string
    {
        $length = rand($minLength, $maxLength);
        $acronym = '';
        $vowelCount = 0;
        $consecutiveConsonants = 0;

        for ($i = 0; $i < $length; $i++) {
            // Ensure at least one vowel in longer acronyms
            $needsVowel = $length >= 4 && $vowelCount === 0 && $i >= $length - 2;

            // Avoid too many consecutive consonants
            $forceVowel = $consecutiveConsonants >= 3;

            if ($needsVowel || $forceVowel) {
                $letter = $this->pickRandomVowel();
                $vowelCount++;
                $consecutiveConsonants = 0;
            } else {
                $letter = $this->pickWeightedLetter();
                if (in_array($letter, self::VOWELS)) {
                    $vowelCount++;
                    $consecutiveConsonants = 0;
                } else {
                    $consecutiveConsonants++;
                }
            }

            $acronym .= $letter;
        }

        return $acronym;
    }

    private function pickWeightedLetter(): string
    {
        $weights = self::LETTER_WEIGHTS;
        foreach ($this->excludedLetters as $excluded) {
            unset($weights[$excluded]);
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight === 0) {
            return 'S';
        }

        $random = rand(1, $totalWeight);
        $currentSum = 0;

        foreach ($weights as $letter => $weight) {
            $currentSum += $weight;
            if ($random <= $currentSum) {
                return $letter;
            }
        }

        return 'S'; // Fallback
    }

    private function pickRandomVowel(): string
    {
        $vowels = array_diff(self::VOWELS, $this->excludedLetters);
        if (empty($vowels)) {
            return self::VOWELS[array_rand(self::VOWELS)];
        }

        return $vowels[array_rand($vowels)];
    }

    public function generateBatch(int $count, int $minLength = 3, int $maxLength = 6): array
    {
        $acronyms = [];
        $attempts = 0;
        $maxAttempts = $count * 3;

        while (count($acronyms) < $count && $attempts < $maxAttempts) {
            $acronym = $this->generate($minLength, $maxLength);
            if (! in_array($acronym, $acronyms)) {
                $acronyms[] = $acronym;
            }
            $attempts++;
        }

        return $acronyms;
    }
}
