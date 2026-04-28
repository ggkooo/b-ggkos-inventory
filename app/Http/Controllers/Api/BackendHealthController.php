<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackendHealthReporter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BackendHealthController extends Controller
{
    public function __construct(public BackendHealthReporter $healthReporter) {}

    public function __invoke(): JsonResponse
    {
        $payload = $this->healthReporter->report();
        $status = (string) data_get($payload, 'summary.status', 'healthy');

        return response()->json($payload, $this->httpStatus($status));
    }

    private function httpStatus(string $status): int
    {
        return match ($status) {
            'misconfigured' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'down' => Response::HTTP_SERVICE_UNAVAILABLE,
            default => Response::HTTP_OK,
        };
    }
}
