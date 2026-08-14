<?php

namespace App\Http\Controllers\Frontend;

use Exception;
use App\Services\OverviewService;
use App\Http\Controllers\Controller;

class OverviewController extends Controller
{
    private OverviewService $overviewService;

    public function __construct(OverviewService $overviewService)
    {
        $this->overviewService = $overviewService;
    }

    public function totalOrders(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ['data' => ['total_orders' => $this->overviewService->totalOrders()]];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function totalCompletedOrders(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ['data' => ['total_completed_orders' => $this->overviewService->totalCompletedOrders()]];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function totalReturnedOrders(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ['data' => ['total_returned_orders' => $this->overviewService->totalReturnedOrders()]];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function walletBalance(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return ['data' => ['wallet_balance' => $this->overviewService->walletBalance()]];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function virtualAccount(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (empty($user->monnify_account_number)) {
                return ['data' => null];
            }
            return ['data' => $this->overviewService->monnifyVirtualAccountForUser($user)];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function generateVirtualAccount(): \Illuminate\Http\Response | array | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            return ['data' => $this->overviewService->monnifyVirtualAccountForUser($user)];
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
