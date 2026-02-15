<?php

declare(strict_types=1);

namespace App\Domain\Round\Services;

use App\Infrastructure\Models\GullkornClean;
use Illuminate\Support\Facades\Log;

class BotAnswerService
{
    /**
     * Find a sentence from gullkorn_clean that matches the given acronym,
     * or generate a fallback sentence.
     */
    public function findAnswer(string $acronym): string
    {
        $acronym = strtoupper($acronym);
        $letters = str_split($acronym);

        // Try to find a matching gullkorn sentence
        $match = $this->findMatchingGullkorn($letters);
        if ($match) {
            return $match;
        }

        // Fallback: generate a simple sentence
        return $this->generateFallback($letters);
    }

    private function findMatchingGullkorn(array $letters): ?string
    {
        $length = count($letters);

        try {
            $query = GullkornClean::query()
                ->whereRaw("array_length(regexp_split_to_array(trim(setning), E'\\\\s+'), 1) = ?", [$length]);

            // Filter by first letter of each word position directly in SQL
            foreach ($letters as $i => $letter) {
                $pos = $i + 1; // PostgreSQL arrays are 1-indexed
                $query->whereRaw(
                    "upper(left((regexp_split_to_array(trim(setning), E'\\\\s+'))[?], 1)) = ?",
                    [$pos, $letter]
                );
            }

            // Pick a random one from the top 20 most-voted matches
            $candidate = $query
                ->orderByDesc('stemmer')
                ->limit(20)
                ->get()
                ->random();

            return mb_strtolower($candidate->setning);
        } catch (\Throwable $e) {
            Log::warning('BotAnswerService: gullkorn_clean query failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function generateFallback(array $letters): string
    {
        $wordBank = [
            'A' => ['Alle', 'Andre', 'At', 'Aldri'],
            'B' => ['Burde', 'Bare', 'Bli', 'Bruke'],
            'C' => ['Cola', 'Cirka', 'Camping'],
            'D' => ['De', 'Den', 'Denne', 'Dra'],
            'E' => ['En', 'Er', 'Egentlig', 'Etter'],
            'F' => ['For', 'Fra', 'Faktisk', 'Folk'],
            'G' => ['Gi', 'Ganske', 'Greit', 'Glad'],
            'H' => ['Ha', 'Her', 'Helt', 'Hva'],
            'I' => ['I', 'Ikke', 'Inn', 'Igjen'],
            'J' => ['Jo', 'Ja', 'Jobb', 'Jeg'],
            'K' => ['Kan', 'Komme', 'Kjempe', 'Kanskje'],
            'L' => ['Litt', 'Like', 'Lang', 'Lage'],
            'M' => ['Med', 'Meg', 'Mye', 'Mange'],
            'N' => ['Nei', 'Nye', 'Noen', 'Nok'],
            'O' => ['Og', 'Ofte', 'Over', 'Om'],
            'P' => ['Passe', 'Plutselig', 'Per', 'Prøve'],
            'Q' => ['Quiz'],
            'R' => ['Riktig', 'Rar', 'Rundt', 'Rask'],
            'S' => ['Se', 'Som', 'Snart', 'Sakte'],
            'T' => ['Til', 'Tre', 'Ting', 'Tenke'],
            'U' => ['Ut', 'Under', 'Uten', 'Uansett'],
            'V' => ['Vi', 'Vil', 'Vise', 'Veldig'],
            'W' => ['Wow'],
            'X' => ['Xtra'],
            'Y' => ['Ytterst', 'Ypperlig'],
            'Z' => ['Zen', 'Zoo'],
            'Æ' => ['Ærlig'],
            'Ø' => ['Øke', 'Ønsker'],
            'Å' => ['Å', 'Åpne'],
        ];

        $words = [];
        foreach ($letters as $letter) {
            $options = $wordBank[$letter] ?? [$letter . 'ord'];
            $words[] = $options[array_rand($options)];
        }

        return mb_strtolower(implode(' ', $words));
    }
}
