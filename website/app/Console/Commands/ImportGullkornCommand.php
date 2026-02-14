<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGullkornCommand extends Command
{
    protected $signature = 'gullkorn:import
        {--path=sql : Path to directory containing SQL files (relative to base_path)}
        {--only= : Import only one table (gullkorn or gullkorn_clean)}';

    protected $description = 'Import gullkorn MySQL dumps into PostgreSQL, dropping and recreating tables';

    private const TABLES = ['gullkorn', 'gullkorn_clean'];

    public function handle(): int
    {
        $basePath = base_path($this->option('path'));
        $only = $this->option('only');

        $tables = self::TABLES;

        if ($only) {
            if (! in_array($only, $tables)) {
                $this->error("Unknown table: {$only}. Must be 'gullkorn' or 'gullkorn_clean'.");

                return Command::FAILURE;
            }
            $tables = [$only];
        }

        foreach ($tables as $table) {
            $file = "{$basePath}/{$table}.sql";

            if (! file_exists($file)) {
                $this->error("SQL file not found: {$file}");

                return Command::FAILURE;
            }

            $this->info("Importing {$table} from {$file}...");

            $this->createTable($table);
            $this->importData($table, $file);

            $count = DB::table($table)->count();
            $this->info("  Imported {$count} rows into {$table}");
        }

        $this->newLine();
        $this->info('Done!');

        return Command::SUCCESS;
    }

    private function createTable(string $table): void
    {
        DB::statement("DROP TABLE IF EXISTS {$table} CASCADE");
        $this->line("  Dropped table {$table} (if existed)");

        DB::statement("
            CREATE TABLE {$table} (
                id SERIAL PRIMARY KEY,
                nick VARCHAR(9) NOT NULL DEFAULT '',
                setning TEXT NOT NULL,
                stemmer INTEGER NOT NULL DEFAULT 0,
                tid TIMESTAMP NULL,
                hvemstemte TEXT NOT NULL DEFAULT ''
            )
        ");
        $this->line("  Created table {$table}");
    }

    private function importData(string $table, string $file): void
    {
        $content = file_get_contents($file);

        // Extract all INSERT lines
        preg_match_all('/INSERT INTO `[^`]+` VALUES (.+);/s', $content, $matches);

        if (empty($matches[1])) {
            $this->warn("  No INSERT statements found in {$file}");

            return;
        }

        $valuesStr = $matches[1][0];

        // Split into individual row tuples: (id,'nick','setning',stemmer,'tid','hvemstemte')
        // Each tuple starts with ( and ends with )
        // We need to handle commas inside quoted strings
        $rows = $this->parseRows($valuesStr);

        $this->line('  Parsed '.count($rows).' rows, inserting...');

        $batch = [];
        $batchSize = 500;

        foreach ($rows as $i => $row) {
            $batch[] = $row;

            if (count($batch) >= $batchSize) {
                DB::table($table)->insert($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table($table)->insert($batch);
        }

        // Reset the sequence to max id
        DB::statement("SELECT setval('{$table}_id_seq', (SELECT COALESCE(MAX(id), 0) FROM {$table}))");
    }

    private function parseRows(string $valuesStr): array
    {
        $rows = [];
        $len = strlen($valuesStr);
        $pos = 0;

        while ($pos < $len) {
            // Find opening paren
            $start = strpos($valuesStr, '(', $pos);
            if ($start === false) {
                break;
            }

            // Find matching closing paren, respecting quoted strings
            $end = $this->findClosingParen($valuesStr, $start);
            if ($end === false) {
                break;
            }

            $tupleStr = substr($valuesStr, $start + 1, $end - $start - 1);
            $fields = $this->parseTuple($tupleStr);

            if (count($fields) >= 6) {
                $tid = $fields[4];
                // Handle MySQL's invalid date '2000-00-00 00:00:00'
                if ($tid === null || str_contains($tid, '0000') || str_contains($tid, '-00-')) {
                    $tid = null;
                }

                $rows[] = [
                    'id' => (int) $fields[0],
                    'nick' => $fields[1] ?? '',
                    'setning' => $fields[2] ?? '',
                    'stemmer' => (int) ($fields[3] ?? 0),
                    'tid' => $tid,
                    'hvemstemte' => $fields[5] ?? '',
                ];
            }

            $pos = $end + 1;
        }

        return $rows;
    }

    private function findClosingParen(string $str, int $openPos): int|false
    {
        $len = strlen($str);
        $pos = $openPos + 1;
        $inQuote = false;

        while ($pos < $len) {
            $char = $str[$pos];

            if ($inQuote) {
                if ($char === '\\') {
                    $pos += 2;

                    continue;
                }
                if ($char === "'") {
                    $inQuote = false;
                }
            } else {
                if ($char === "'") {
                    $inQuote = true;
                } elseif ($char === ')') {
                    return $pos;
                }
            }
            $pos++;
        }

        return false;
    }

    private function parseTuple(string $tuple): array
    {
        $fields = [];
        $len = strlen($tuple);
        $pos = 0;

        while ($pos < $len) {
            // Skip whitespace
            while ($pos < $len && $tuple[$pos] === ' ') {
                $pos++;
            }

            if ($pos >= $len) {
                break;
            }

            if ($tuple[$pos] === "'") {
                // Quoted string
                $pos++; // skip opening quote
                $value = '';
                while ($pos < $len) {
                    $char = $tuple[$pos];
                    if ($char === '\\' && $pos + 1 < $len) {
                        $next = $tuple[$pos + 1];
                        if ($next === "'") {
                            $value .= "'";
                        } elseif ($next === '\\') {
                            $value .= '\\';
                        } elseif ($next === 'n') {
                            $value .= "\n";
                        } elseif ($next === 'r') {
                            $value .= "\r";
                        } else {
                            $value .= $next;
                        }
                        $pos += 2;

                        continue;
                    }
                    if ($char === "'" && $pos + 1 < $len && $tuple[$pos + 1] === "'") {
                        $value .= "'";
                        $pos += 2;

                        continue;
                    }
                    if ($char === "'") {
                        $pos++; // skip closing quote
                        break;
                    }
                    $value .= $char;
                    $pos++;
                }
                $fields[] = $value;
            } elseif (strtoupper(substr($tuple, $pos, 4)) === 'NULL') {
                $fields[] = null;
                $pos += 4;
            } else {
                // Unquoted (numeric)
                $start = $pos;
                while ($pos < $len && $tuple[$pos] !== ',') {
                    $pos++;
                }
                $fields[] = trim(substr($tuple, $start, $pos - $start));
            }

            // Skip comma
            if ($pos < $len && $tuple[$pos] === ',') {
                $pos++;
            }
        }

        return $fields;
    }
}
