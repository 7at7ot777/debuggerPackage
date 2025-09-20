<?php

namespace MohamedHathout\Debugger\Http\Controllers;

use MohamedHathout\Debugger\DebuggerInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class FileDebugger implements DebuggerInterface
{
    protected string $logFile;

    public function __construct()
    {
        $this->logFile = config('debugger.log_path');
        if (!File::exists(dirname($this->logFile))) {
            File::makeDirectory(dirname($this->logFile), 0755, true);
        }

        // Create the file if it doesn't exist
        if (!File::exists($this->logFile)) {
            File::put($this->logFile, '');
        }    }

    public function display($variable): void
    {
        $trace = debug_backtrace()[1];
        $file = str_replace(base_path(), '', $trace['file']);
        $line = $trace['line'];
        $timestamp = now()->toDateTimeString(); // Laravel helper

        $entry = "[{$timestamp}] [{$file}:{$line}] ";

        if (is_array($variable) || is_object($variable)) {
            $entry .= "JSON: " . json_encode($variable, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } elseif (is_int($variable) || is_float($variable) || is_bool($variable)) {
            $entry .= "NUMBER: " . var_export($variable, true);
        } elseif (is_string($variable)) {
            $entry .= "TEXT: " . $variable;
        } else {
            $entry .= "UNKNOWN: " . gettype($variable);
        }

        $this->logToFile($entry);
    }

    public function displayQuery(Builder $query): void
    {
        $trace = debug_backtrace()[1];
        $file = str_replace(base_path(), '', $trace['file']);
        $line = $trace['line'];
        $timestamp = now()->toDateTimeString(); // Laravel helper

        $sql = $query->toSql();
        foreach ($query->getBindings() as $binding) {
            $value = is_numeric($binding) ? $binding : "'$binding'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        $entry = "[{$timestamp}] [{$file}:{$line}] TEXT: {$sql}";
        $this->logToFile($entry);
    }


    public function loadDebugData($search = null, $filterByType = null, $filterByFile = null): array
    {
        if (!File::exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES);
        $entries = [];
        $currentEntry = '';

        foreach ($lines as $line) {
            // Check if this is the start of a new entry
            if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}]\s+\[.+?:\d+]\s+[A-Z]+:/', $line)) {
                if ($currentEntry) {
                    $entries[] = $currentEntry;
                }
                $currentEntry = $line;
            } else {
                // Append continued lines (indented JSON or array)
                $currentEntry .= "\n" . $line;
            }
        }

        if ($currentEntry) {
            $entries[] = $currentEntry;
        }

        $results = [];

        foreach ($entries as $index => $entry) {
            if (preg_match('/^\[(.+?)]\s+\[(.+?):(\d+)]\s+([A-Z]+):\s+(.*)$/s', $entry, $matches)) {
                [$full, $created_at, $class_name, $line_number, $debug_type, $raw_value] = $matches;

                // Apply filters
                if (
                    ($search && stripos($entry, $search) === false) ||
                    ($filterByType && stripos($debug_type, $filterByType) === false) ||
                    ($filterByFile && stripos($class_name, $filterByFile) === false)
                ) {
                    continue;
                }

                $formatted_value = match (strtolower($debug_type)) {
                    'json' => json_decode($raw_value, true),
                    'number' => is_numeric($raw_value) ? +$raw_value : $raw_value,
                    default => $raw_value,
                };

                $results[] = [
                    'id' => $index + 1,
                    'class_name' => $class_name,
                    'line_number' => (int)$line_number,
                    'debug_type' => strtolower($debug_type),
                    'created_at' => Carbon::parse($created_at),
                    'value' => $formatted_value,
                    'raw_value' => $raw_value,
                ];
            }
        }

        return $results;
    }

    public function loadFiles(): array
    {
        if (!File::exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES);
        $files = [];

        foreach ($lines as $line) {
            if (preg_match('/^\[(.+?):\d+]/', $line, $matches)) {
                $files[] = $matches[1];
            }
        }

        return array_values(array_unique($files));
    }

    public function getStackTrace(): array
    {
        if (!File::exists($this->logFile)) {
            return [];
        }

        return file($this->logFile, FILE_IGNORE_NEW_LINES);
    }

    private function logToFile(string $entry): void
    {
        File::append($this->logFile, $entry . PHP_EOL);
    }
    public function clearAllDebugData(): void
    {
        if(!config('debugger.truncate_tables'))
            return;
        File::put($this->logFile, '');
    }
}
