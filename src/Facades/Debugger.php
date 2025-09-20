<?php

namespace MohamedHathout\Debugger\Facades;

use Illuminate\Support\Facades\Facade;
use MohamedHathout\Debugger\DebuggerInterface;

class Debugger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DebuggerInterface::class;
    }
}
