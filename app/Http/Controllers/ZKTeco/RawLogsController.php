<?php

namespace App\Http\Controllers\ZKTeco;

use App\Http\Controllers\Controller;
use App\Models\ZktecoRawLog;
use Illuminate\Http\Request;

class RawLogsController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $items = ZktecoRawLog::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'limit' => $limit,
                'count' => $items->count(),
            ],
        ]);
    }
}
