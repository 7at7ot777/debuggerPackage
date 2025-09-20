<?php

namespace MohamedHathout\Debugger;

use Illuminate\Database\Eloquent\Builder;

interface DebuggerInterface
{
    public function display(mixed $variable): void;
    public function displayQuery(Builder $query): void;
    public function loadDebugData($search = null, $filterByType = null, $filterByFile = null): array;
    public function loadFiles(): array;
    public function clearAllDebugData(): void;
}
