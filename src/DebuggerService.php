<?php

namespace MohamedHathout\Debugger;

use Illuminate\Database\Eloquent\Builder;
use MohamedHathout\Debugger\Models\Debug;
use MohamedHathout\Debugger\Models\Json;
use MohamedHathout\Debugger\Models\Number;
use MohamedHathout\Debugger\Models\Text;
use Illuminate\Support\Facades\Cache;

class DebuggerService implements DebuggerInterface
{
    public function display(mixed $variable): void
    {
        if (!config('debugger.is_enabled', true)) {
            return;
        }

        $debug = new Debug();
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $debug->line_number = $trace['line'];
        $debug->class_name = $trace['file'];

        if (is_numeric($variable)) {
            $number = new Number();
            $number->number = $variable;
            $number->is_int = is_int($variable);
            $number->save();

            $debug->debug_type = 'number';
            $debug->debug_id = $number->id;
        } elseif (is_array($variable) || is_object($variable)) {
            $json = new Json();
            $json->json = json_encode($variable);
            $json->save();

            $debug->debug_type = 'json';
            $debug->debug_id = $json->id;
        } else {
            $text = new Text();
            $text->text = (string) $variable;
            $text->save();

            $debug->debug_type = 'text';
            $debug->debug_id = $text->id;
        }

        $debug->save();

        if (config('debugger.storage_type') === 'cache') {
            $this->storeInCache($debug);
        }
    }

    public function displayQuery(Builder $query): void
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        $this->display($sql);
    }

    public function loadDebugData($search = null, $filterByType = null, $filterByFile = null): array
    {
        if (config('debugger.storage_type') === 'cache') {
            return $this->loadFromCache($search, $filterByType, $filterByFile);
        }

        $query = Debug::with('debugable')
            ->when($search, function ($query) use ($search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('line_number', 'like', "%{$search}%")
                        ->orWhere('class_name', 'like', "%{$search}%");
                });
            })
            ->when($filterByType, function ($query) use ($filterByType) {
                return $query->where('debug_type', $filterByType);
            })
            ->when($filterByFile, function ($query) use ($filterByFile) {
                return $query->where('class_name', 'like', "%{$filterByFile}%");
            })
            ->orderBy('created_at', config('debugger.sort', 'desc'));

        return $query->get()->map(function ($debug) {
            return [
                'id' => $debug->id,
                'line_number' => $debug->line_number,
                'class_name' => $debug->class_name,
                'debug_type' => $debug->debug_type,
                'value' => $this->getDebugValue($debug),
                'raw_value' => $this->getRawValue($debug),
                'created_at' => $debug->created_at,
            ];
        })->toArray();
    }

    public function loadFiles(): array
    {
        if (config('debugger.storage_type') === 'cache') {
            return Cache::get(config('debugger.cache.files_key'), []);
        }

        return Debug::distinct()->pluck('class_name')->toArray();
    }

    public function clearAllDebugData(): void
    {
        if (config('debugger.storage_type') === 'cache') {
            $this->clearCache();
            return;
        }

        if (config('debugger.truncate_tables', false)) {
            Text::truncate();
            Number::truncate();
            Json::truncate();
            Debug::truncate();
        } else {
            Text::query()->delete();
            Number::query()->delete();
            Json::query()->delete();
            Debug::query()->delete();
        }
    }

    protected function getDebugValue($debug): mixed
    {
        return match ($debug->debug_type) {
            'text' => $debug->debugable->text,
            'number' => $debug->debugable->value,
            'json' => $debug->debugable->decoded_json,
            default => null,
        };
    }

    protected function getRawValue($debug): string
    {
        return match ($debug->debug_type) {
            'text' => $debug->debugable->text,
            'number' => (string) $debug->debugable->value,
            'json' => json_encode($debug->debugable->decoded_json),
            default => '',
        };
    }

    protected function storeInCache(Debug $debug): void
    {
        $prefix = config('debugger.cache.key_prefix');
        $counterKey = config('debugger.cache.counter_key');
        $indexKey = config('debugger.cache.index_key');
        $filesKey = config('debugger.cache.files_key');
        $ttl = config('debugger.cache.ttl', 3600);

        $counter = Cache::get($counterKey, 0) + 1;
        Cache::put($counterKey, $counter, $ttl);

        $debugData = [
            'id' => $counter,
            'line_number' => $debug->line_number,
            'class_name' => $debug->class_name,
            'debug_type' => $debug->debug_type,
            'value' => $this->getDebugValue($debug),
            'raw_value' => $this->getRawValue($debug),
            'created_at' => $debug->created_at,
        ];

        Cache::put($prefix . $counter, $debugData, $ttl);

        $index = Cache::get($indexKey, []);
        $index[] = $counter;
        Cache::put($indexKey, $index, $ttl);

        $files = Cache::get($filesKey, []);
        if (!in_array($debug->class_name, $files)) {
            $files[] = $debug->class_name;
            Cache::put($filesKey, $files, $ttl);
        }
    }

    protected function loadFromCache($search = null, $filterByType = null, $filterByFile = null): array
    {
        $prefix = config('debugger.cache.key_prefix');
        $index = Cache::get(config('debugger.cache.index_key'), []);
        $debugs = [];

        foreach ($index as $id) {
            $debug = Cache::get($prefix . $id);
            if (!$debug) continue;

            if ($search && !$this->matchesSearch($debug, $search)) continue;
            if ($filterByType && $debug['debug_type'] !== $filterByType) continue;
            if ($filterByFile && !str_contains($debug['class_name'], $filterByFile)) continue;

            $debugs[] = $debug;
        }

        return $debugs;
    }

    protected function matchesSearch(array $debug, string $search): bool
    {
        return str_contains($debug['line_number'], $search) ||
            str_contains($debug['class_name'], $search);
    }

    protected function clearCache(): void
    {
        $prefix = config('debugger.cache.key_prefix');
        $index = Cache::get(config('debugger.cache.index_key'), []);

        foreach ($index as $id) {
            Cache::forget($prefix . $id);
        }

        Cache::forget(config('debugger.cache.counter_key'));
        Cache::forget(config('debugger.cache.index_key'));
        Cache::forget(config('debugger.cache.files_key'));
    }
}
