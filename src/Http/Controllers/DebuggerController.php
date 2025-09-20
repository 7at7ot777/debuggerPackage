<?php

namespace MohamedHathout\Debugger\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MohamedHathout\Debugger\Debugger;

class DebuggerController extends Controller
{
    public function clearAll()
    {
        Debugger::clearAllDebugData();
        return response()->json(['message' => 'All debug data cleared successfully']);
    }

    public function getDebugData(Request $request)
    {
        $search = $request->get('search');
        $filterByType = $request->get('type');
        $filterByFile = $request->get('file');

        $data = Debugger::loadDebugData($search, $filterByType, $filterByFile);
        return response()->json($data);
    }

    public function getFiles()
    {
        $files = Debugger::loadFiles();
        return response()->json($files);
    }
}
