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
     * Get shallow, safe public support health status.
     * Strictly avoids leaking internal table maps, drivers, environment, or secret metadata.
     */
    public function index(Request $request): JsonResponse
    {
        $readiness = $this->readinessService->getReadiness();

        $isReady = (bool)($readiness['ready'] ?? false);
        $status = $readiness['status'] ?? ($isReady ? 'ready' : 'unavailable');
        if ($status === 'blocked') {
            $status = 'unavailable';
        }

        $voiceReady = (bool)($readiness['voice']['stt_ready'] ?? false) || (bool)($readiness['voice']['tts_ready'] ?? false);
        $realtimeReady = (bool)($readiness['realtime']['supported'] ?? false);

        $payload = [
            'success' => $isReady,
            'status' => $status,
            'services' => [
                'support' => $isReady,
                'text' => $isReady,
                'voice' => $voiceReady,
                'realtime' => $realtimeReady,
                'polling_fallback' => true,
            ],
        ];

        $statusCode = $isReady ? 200 : 503;

        return response()->json($payload, $statusCode);
    }
}

