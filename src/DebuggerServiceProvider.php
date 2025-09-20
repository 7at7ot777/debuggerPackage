<?php

namespace MohamedHathout\Debugger;

use MohamedHathout\Debugger\Debugger;
use MohamedHathout\Debugger\DebuggerInterface;
use MohamedHathout\Debugger\Http\Controllers\CacheDebugger;
use MohamedHathout\Debugger\Http\Controllers\DatabaseDebugger;
use MohamedHathout\Debugger\Http\Controllers\FileDebugger;
use MohamedHathout\Debugger\Http\Controllers\NullDebugger;
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
