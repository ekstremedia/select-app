<?php

namespace App\Application\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'reverb' => [
                'key' => config('reverb.apps.apps.0.key', ''),
                'port' => (int) config('reverb.apps.apps.0.options.port', config('reverb.servers.reverb.port', 8080)),
            ],
            'gullkorn' => fn () => $this->getRandomGullkorn(),
        ];
    }

    private function getRandomGullkorn(): ?string
    {
        try {
            if (Schema::hasTable('gullkorn_clean')) {
                $gullkorn = DB::table('gullkorn_clean')
                    ->where('stemmer', '>', 4)
                    ->inRandomOrder()
                    ->first();

                return $gullkorn?->setning;
            }
        } catch (\Throwable) {
            // Table may not exist yet
        }

        return null;
    }
}
