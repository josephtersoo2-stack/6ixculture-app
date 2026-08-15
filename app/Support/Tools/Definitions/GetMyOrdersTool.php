<?php

namespace App\Support\Tools\Definitions;

use App\Http\Requests\PaginateRequest;
use App\Services\FrontendOrderService;
use App\Support\Contracts\ToolInterface;
use App\Support\DTOs\ToolCallDTO;
use App\Support\DTOs\ToolResultDTO;
use App\Support\Models\SupportConversation;
use Illuminate\Support\Facades\Auth;

class GetMyOrdersTool implements ToolInterface
{
    protected FrontendOrderService $orderService;

    public function __construct()
    {
        $this->orderService = new FrontendOrderService();
    }

    public function key(): string
    {
        return 'get_my_orders';
    }

    public function name(): string
    {
        return 'Get My Orders';
    }

    public function description(): string
    {
        return 'Retrieve order history, including items, purchase dates, status, and tracking codes for the logged-in customer.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional maximum number of orders to return (default 5, max 10).'
                ],
            ],
        ];
    }

    public function execute(ToolCallDTO $call, SupportConversation $conversation): ToolResultDTO
    {
        try {
            $customerId = $conversation->customer_id;
            if (!$customerId) {
                return ToolResultDTO::error($call->id, $this->key(), 'Customer is not authenticated. Please log in to view your orders.');
            }

            // Secure identity derivation: bind user into container temporarily
            Auth::onceUsingId($customerId);

            $limit = min((int)($call->arguments['limit'] ?? 5), 10);
            $request = new PaginateRequest([
                'paginate' => 1,
                'per_page' => $limit,
            ]);

            $orders = $this->orderService->myOrder($request);

            $results = [];
            foreach ($orders as $order) {
                $results[] = [
                    'id' => $order->id,
                    'order_number' => $order->order_serial_no,
                    'total' => (float)$order->total,
                    'status' => $this->normalizeOrderStatus($order->status),
                    'payment_status' => $order->payment_status === 5 ? 'Paid' : 'Unpaid',
                    'date' => $order->created_at->toDateTimeString(),
                    'tracking_code' => $order->order_serial_no,
                ];
            }

            return ToolResultDTO::success(
                $call->id,
                $this->key(),
                ['orders' => $results],
                ['order_list' => $results]
            );
        } catch (\Throwable $e) {
            return ToolResultDTO::error($call->id, $this->key(), $e->getMessage());
        }
    }

    private function normalizeOrderStatus(int $status): string
    {
        return match ($status) {
            1 => 'Pending',
            5 => 'Confirmed',
            7 => 'On the Way',
            10 => 'Delivered',
            15 => 'Cancelled',
            20 => 'Rejected',
            default => 'Unknown',
        };
    }
}
