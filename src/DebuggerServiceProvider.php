<?php

namespace App\Providers;

use App\Debugger;
use App\DebuggerInterface;
use App\Http\Controllers\CacheDebugger;
use App\Http\Controllers\DatabaseDebugger;
use App\Http\Controllers\FileDebugger;
use App\Http\Controllers\NullDebugger;
use Illuminate\Support\ServiceProvider;

class DebuggerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DebuggerInterface::class, function () {
            if (!config('debugger.is_enabled'))
            {
                return new NullDebugger();
            }
            return match (config('debugger.storage_type')) {
                'database' => new DatabaseDebugger(),
                'file' => new FileDebugger(),
                'cache' => new CacheDebugger(),
                default => new NullDebugger(),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (
            !request()->is(config('debugger.route_name')) &&
            !request()->is('livewire/update') &&
            config('debugger.truncate_tables')
        ) {
            Debugger::clearAllDebugData();
        }
    }
}
