<?php

namespace MohamedHathout\Debugger\Http\Controllers;

use MohamedHathout\Debugger\DebuggerInterface;
use MohamedHathout\Debugger\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use function Webmozart\Assert\Tests\StaticAnalysis\uuid;

class CacheDebugger implements DebuggerInterface
{
    private const CACHE_KEY_PREFIX = 'debugger:';
    private const CACHE_COUNTER_KEY = 'debugger:counter';
    private const CACHE_INDEX_KEY = 'debugger:index';
    private const CACHE_FILES_KEY = 'debugger:files';
    private const CACHE_TTL = 3600; // 1 hour
    public function __construct()
    {

        // Initialize cache with counter value if it doesn't exist
//        Cache::put(
//            config('debugger.cache.counter_key'),
//            Cache::get(config('debugger.cache.counter_key')) ?? 1,
//            config('debugger.cache.ttl')
//        );

        // For debugging purposes
        // dd([
        //     'counter' => Cache::get(config('debugger.cache.counter_key')),
        //     'index'   => Cache::get(config('debugger.cache.index_key')),
        //     'files'   => Cache::get(config('debugger.cache.files_key')),
        // ]);


    }

    public function display($variable): void
    {
        $debug_backtrace = debug_backtrace()[1];
        $class_name = str_replace(base_path(), '', $debug_backtrace['file']);
        $line = $debug_backtrace['line'];

        $debug_entry = [
            'id' => $this->getNextId(),
            'class_name' => $class_name,
            'line_number' => $line,
            'debug_type' => $this->getVariableType($variable),
            'created_at' => Carbon::now(),
            'value' => $this->formatValue($variable),
            'raw_value' => $this->getRawValue($variable),
        ];

        $this->storeDebugEntry($debug_entry);
        $this->updateIndex($debug_entry);
        $this->updateFilesList($class_name);
    }

    public function displayQuery(Builder $query): void
    {
        $debug_backtrace = debug_backtrace()[1];
        $class_name = str_replace(base_path(), '', $debug_backtrace['file']);
        $line = $debug_backtrace['line'];

        $sql = $query->toSql();
        foreach ($query->getBindings() as $binding) {
            $value = is_numeric($binding) ? $binding : "'$binding'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        $debug_entry = [
            'id' => $this->getNextId(),
            'class_name' => $class_name,
            'line_number' => $line,
            'debug_type' => 'text',
            'created_at' => Carbon::now(),
            'value' => $sql,
            'raw_value' => $sql,
        ];

        $this->storeDebugEntry($debug_entry);
        $this->updateIndex($debug_entry);
        $this->updateFilesList($class_name);
    }

    public function loadDebugData($search = null, $filterByType = null, $filterByFile = null): array
    {
        $index = Cache::get(config('debugger.cache.index_key'), []);
        $results = [];

        foreach ($index as $id) {
            $entry = Cache::get(config('debugger.cache.key_prefix') . $id);
            if (!$entry) continue;

            // Apply filters
            if ($search && !$this->matchesSearch($entry, $search)) {
                continue;
            }

            if ($filterByType && $entry['debug_type'] !== $filterByType) {
                continue;
            }

            if ($filterByFile) {
                if (strpos($entry['class_name'], $filterByFile) === false) {
                    continue;
                }
            }
            $results[] = $entry;
        }

        // Sort by ID (ascending or descending based on config)
        $sortOrder = config('debugger.sort', 'desc');
        usort($results, function ($a, $b) use ($sortOrder) {
            return $sortOrder === 'desc' ? $b['id'] - $a['id'] : $a['id'] - $b['id'];
        });
        return $results;
    }

    public function loadFiles(): array
    {
        return Cache::get(config('debugger.cache.files_key'), []);
    }

    public function clearAllDebugData(): void
    {
        if(!config('debugger.truncate_tables'))
            return;

        $index = Cache::get(config('debugger.cache.index_key'), []);

        // Remove all debug entries
        foreach ($index as $id) {
            Cache::forget(config('debugger.cache.key_prefix') . $id);
        }

        // Clear index and metadata
        Cache::forget(config('debugger.cache.index_key'));
        Cache::forget(config('debugger.cache.counter_key'));
        Cache::forget(config('debugger.cache.files_key'));
    }

    private function getNextId(): int
    {
        $key = config('debugger.cache.counter_key');

        $current = (int) Cache::get($key, 0);

        $next = $current + 1;

        Cache::put($key, $next, config('debugger.cache.ttl'));

        return $next;
    }


    private function getVariableType($variable): string
    {
        if (is_array($variable) || is_object($variable)) {
            return 'json';
        } elseif (is_int($variable) || is_float($variable) || is_numeric($variable) || is_bool($variable)) {
            return 'number';
        } elseif (is_string($variable)) {
            return 'text';
        } else {
            return 'unknown';
        }
    }

    private function formatValue($variable)
    {
        if (is_array($variable) || is_object($variable)) {
            $encoded = json_encode($variable);
            if ($encoded === false) {
                return ['error' => 'Invalid JSON or Array'];
            }
            return json_decode($encoded, true);
        } elseif (is_int($variable) || is_float($variable) || is_numeric($variable) || is_bool($variable)) {
            if (is_bool($variable)) {
                return $variable ? 1 : 0;
            } elseif (is_float($variable)) {
                return (float)$variable;
            }
            return $variable;
        } elseif (is_string($variable)) {
            return $variable;
        } else {
            return 'N/A';
        }
    }

    private function getRawValue($variable): string
    {
        if (is_array($variable) || is_object($variable)) {
            $encoded = json_encode($variable);
            if ($encoded === false) {
                return json_encode(['error' => 'Invalid JSON or Array']);
            }
            return $encoded;
        } elseif (is_int($variable) || is_float($variable) || is_numeric($variable) || is_bool($variable)) {
            if (is_bool($variable)) {
                return $variable ? '1' : '0';
            }
            return (string)$variable;
        } elseif (is_string($variable)) {
            return $variable;
        } else {
            return '';
        }
    }

    private function storeDebugEntry(array $entry): void
    {
        Cache::put(
            config('debugger.cache.key_prefix') . $entry['id'],
            $entry,
            config('debugger.cache.ttl')
        );
    }

    private function updateIndex(array $entry): void
    {
        $index = Cache::get(config('debugger.cache.index_key'), []);
        $index[] = $entry['id'];

        // Keep only the last 1000 entries to prevent memory issues
        if (count($index) > 1000) {
            $oldId = array_shift($index);
            Cache::forget(config('debugger.cache.key_prefix') . $oldId);
        }

        Cache::put(config('debugger.cache.index_key'), $index, config('debugger.cache.ttl'));
    }

    private function updateFilesList(string $className): void
    {
        $files = Cache::get(config('debugger.cache.files_key'), []);

        if (!in_array($className, $files)) {
            $files[] = $className;
            Cache::put(config('debugger.cache.files_key'), array_values($files), config('debugger.cache.ttl'));
        }
    }

    private function matchesSearch(array $entry, string $search): bool
    {
        $search = strtolower($search);

        // Search in class name
        if (strpos(strtolower($entry['class_name']), $search) !== false) {
            return true;
        }

        // Search in line number
        if (strpos((string)$entry['line_number'], $search) !== false) {
            return true;
        }

        // Search in value content
        $searchValue = strtolower($entry['raw_value']);
        if (strpos($searchValue, $search) !== false) {
            return true;
        }

        return false;
    }
}
