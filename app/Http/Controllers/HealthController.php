<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $databaseStatus = $this->checkDatabase();
        $allOk = $databaseStatus === 'ok';

        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'checks' => [
                'app' => 'ok',
                'database' => [
                    'status' => $databaseStatus,
                    'connection' => DB::getDefaultConnection(),
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->select('SELECT 1');

            return 'ok';
        } catch (Throwable $e) {
            report($e);

            return 'error';
        }
    }
}
