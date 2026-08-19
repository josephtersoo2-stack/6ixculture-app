<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Support\Cutover\SupportReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportHealthController extends Controller
{
    protected SupportReadinessService $readinessService;

    public function __construct(SupportReadinessService $readinessService)
    {
        $this->readinessService = $readinessService;
    }

    /**
     * Get sanitized support system health and readiness status.
     */
    public function index(Request $request): JsonResponse
    {
        $readiness = $this->readinessService->getReadiness();

        $statusCode = $readiness['ready'] ? 200 : 503;

        return response()->json([
            'success' => $readiness['ready'],
            'data' => $readiness,
        ], $statusCode);
    }
}
