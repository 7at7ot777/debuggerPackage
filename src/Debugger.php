<?php

namespace MohamedHathout\Debugger;

use Illuminate\Support\Facades\Facade;

class Debugger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DebuggerInterface::class;
    }
}
