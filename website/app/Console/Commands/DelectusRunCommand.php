<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Delectus\DelectusService;
use Illuminate\Console\Command;

class DelectusRunCommand extends Command
{
    protected $signature = 'delectus:run
        {--interval=1000 : Tick interval in milliseconds}';

    protected $description = 'Run Delectus, the game orchestrator daemon';

    public function __construct(
        private DelectusService $delectus
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $interval = (int) $this->option('interval');

        $this->info('');
        $this->info('  ╔═══════════════════════════════════════╗');
        $this->info('  ║         DELECTUS IS WATCHING          ║');
        $this->info('  ║     The Game Orchestrator Daemon      ║');
        $this->info('  ╚═══════════════════════════════════════╝');
        $this->info('');
        $this->info("  Tick interval: {$interval}ms");
        $this->info('  Press Ctrl+C to stop');
        $this->info('');

        while (true) {
            $processed = $this->delectus->tick();

            if ($processed > 0 && $this->output->isVerbose()) {
                $this->line('  ['.now()->format('H:i:s')."] Processed {$processed} game(s)");
            }

            usleep($interval * 1000);
        }

        return Command::SUCCESS;
    }
}
