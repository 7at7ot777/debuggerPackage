<?php

namespace MohamedHathout\Debugger\Http\Controllers;

use MohamedHathout\Debugger\DebuggerInterface;
use MohamedHathout\Debugger\Models\Debug;
use MohamedHathout\Debugger\Models\Json;
use MohamedHathout\Debugger\Models\Number;
use MohamedHathout\Debugger\Models\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DatabaseDebugger implements DebuggerInterface
{
    public function display($variable): void
    {
//        dd(debug_backtrace());
        $debug_backtrace = debug_backtrace()[1];

        $class_name = str_replace(base_path(), '', $debug_backtrace['file']);
        $line = $debug_backtrace['line'];

        if (is_array($variable) || is_object($variable)) {
            $encoded = json_encode($variable);
            if ($encoded === false) {
                // json_encode failed, provide a fallback message as an array, then encode again
                $encoded = json_encode(['error' => 'Invalid JSON or Array']);
            }
            // Use the already encoded string, no need to encode again
            $json = Json::create(['json' => $encoded]);
            $morph_type = 'json';
            $morph_id = $json->id;

        } elseif (is_int($variable) || is_float($variable) || is_numeric($variable) || is_bool($variable)) {
            $is_int = is_int($variable);
            $number = $variable;

            if (is_bool($variable)) {
                $number = $variable ? 1 : 0; // Convert boolean to integer
                $is_int = true;
            } elseif (is_float($variable)) {
                $number = (float)$variable;
            }

            $numberModel = Number::create([
                'number' => $number,
                'is_int' => $is_int,
            ]);

            $morph_type = 'number';
            $morph_id = $numberModel->id;

        } elseif (is_string($variable)) {
            $text = Text::create(['text' => $variable]);
            $morph_type = 'text';
            $morph_id = $text->id;

        } else {

            $morph_id = null;
            $morph_type = null;
        }

        Debug::create([
            'class_name' => $class_name,
            'line_number' => $line,
            'debug_id' => $morph_id,
            'debug_type' => $morph_type,
        ]);
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
        $text = Text::create([
            'text' => $sql,
        ]);



        Debug::create([
            'class_name' => $class_name,
            'line_number' => $line,
            'debug_id' => $text->id,
            'debug_type' => 'text',
        ]);

    }

    public function loadDebugData($search = null, $filterByType = null, $filterByFile = null): array
    {
        $query = Debug::with('debugable')
            ->orderBy('id', config('debugger.sort'));

        if ($search) {
            $search = '%' . $search . '%';

            $query->where(function ($q) use ($search) {
                $q->where('class_name', 'like', $search)
                    ->orWhere('line_number', 'like', $search)
                    ->orWhereHasMorph('debugable', [Text::class, Json::class, Number::class], function ($subQuery, $type) use ($search) {
                        switch ($type) {
                            case Text::class:
                                $subQuery->where('text', 'like', $search);
                                break;
                            case Json::class:
                                $subQuery->where('json', 'like', $search);
                                break;
                            case Number::class:
                                $subQuery->whereRaw('CAST(number AS CHAR) LIKE ?', [$search]);
                                break;
                        }
                    });
            });
        }

        if ($filterByType) {
            $query->where('debug_type', $filterByType);
        }

        if ($filterByFile) {
            $path = str_replace("\\","\\\\",$filterByFile);
            $this->display($path);
            $query->where('class_name', 'like', '%' . $path . '%');
        }

        return $query->get()->map(function ($debug) {
            return [
                'id' => $debug->id,
                'class_name' => $debug->class_name,
                'line_number' => $debug->line_number,
                'debug_type' => $debug->debug_type,
                'created_at' => Carbon::parse($debug->created_at),
                'value' => $this->formatDebugValue($debug),
                'raw_value' => $this->getRawValue($debug),
            ];
        })->toArray();
    }
    public function loadFiles(): array
    {
        return Debug::select('class_name')
            ->distinct()
            ->pluck('class_name')
            ->filter()
            ->values()
            ->toArray();
    }

    private function formatDebugValue($debug)
    {
        if (!$debug->debugable) {
            return 'N/A';
        }

        switch ($debug->debug_type) {
            case 'text':
                return $debug->debugable->text;
            case 'number':
                return $debug->debugable->is_int ?
                    (int) $debug->debugable->number :
                    (float) $debug->debugable->number;
            case 'json':
                return json_decode($debug->debugable->json, true);
            default:
                return 'Unknown type';
        }
    }
    private function getRawValue($debug)
    {
        if (!$debug->debugable) {
            return '';
        }

        switch ($debug->debug_type) {
            case 'text':
                return $debug->debugable->text;
            case 'number':
                return (string) $debug->debugable->number;
            case 'json':
                return $debug->debugable->json;
            default:
                return '';
        }
    }

    public function getStackTrace()
    {
        $debug = Debug::with('debugable')
            ->orderBy('id', config('debugger.sort'))
            ->get();
        return $debug;
    }
    private function refreshDB()
    {
        if(!config('debugger.truncate_tables'))
            return;

            Text::truncate();
            Json::truncate();
            Number::truncate();
            Debug::truncate();
    }

    public function clearAllDebugData(): void
    {
        Text::truncate();
        Json::truncate();
        Number::truncate();
        Debug::truncate();
    }
}
