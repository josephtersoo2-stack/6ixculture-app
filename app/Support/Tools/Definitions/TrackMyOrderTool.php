<?php

namespace App\Support\Tools\Definitions;

use App\Models\Order;
use App\Services\FrontendOrderService;
use App\Support\Contracts\ToolInterface;
use App\Support\DTOs\ToolCallDTO;
use App\Support\DTOs\ToolResultDTO;
use App\Support\Models\SupportConversation;
use Illuminate\Support\Facades\Auth;

class TrackMyOrderTool implements ToolInterface
{
    protected FrontendOrderService $orderService;

    public function __construct()
    {
        $this->orderService = new FrontendOrderService();
    }

    public function key(): string
    {
        return 'track_my_order';
    }

    public function name(): string
    {
        return 'Track My Order';
    }

    public function description(): string
    {
        return 'Retrieve real-time tracking status, shipment milestones, and delivery information for a specific order by order_id or order_number.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_id' => [
                    'type' => 'integer',
                    'description' => 'Optional database order ID.'
                ],
                'order_number' => [
                    'type' => 'string',
                    'description' => 'Optional unique order serial number (e.g. 6IX-XXXXXX).'
                ],
            ],
        ];
    }

    public function execute(ToolCallDTO $call, SupportConversation $conversation): ToolResultDTO
    {
        try {
            $customerId = $conversation->customer_id;
            if (!$customerId) {
                return ToolResultDTO::error($call->id, $this->key(), 'Customer is not authenticated. Please log in to track your order.');
            }

            // Secure identity derivation
            Auth::onceUsingId($customerId);

            $orderId = isset($call->arguments['order_id']) ? (int)$call->arguments['order_id'] : null;
            $orderNum = isset($call->arguments['order_number']) ? trim($call->arguments['order_number']) : null;

            if (empty($orderId) && empty($orderNum)) {
                return ToolResultDTO::error($call->id, $this->key(), 'Either order_id or order_number must be supplied.');
            }

            $query = Order::query();
            if (!empty($orderId)) {
                $query->where('id', $orderId);
            } else {
                $query->where('order_serial_no', $orderNum);
            }

            $order = $query->first();

            if (!$order) {
                return ToolResultDTO::error($call->id, $this->key(), 'Order not found.');
            }

            // Verify customer ownership
            if ((int)$order->user_id !== (int)$customerId) {
                return ToolResultDTO::error($call->id, $this->key(), 'Access Denied: You do not have permission to access this order.');
            }

            // Fetch order milestones using authoritative FrontendOrderService
            $orderWithRelations = $this->orderService->show($order);

            $trackingInfo = [
                'id' => $orderWithRelations->id,
                'order_number' => $orderWithRelations->order_serial_no,
                'status' => $this->normalizeOrderStatus($orderWithRelations->status),
                'status_code' => $orderWithRelations->status,
                'total' => (float)$orderWithRelations->total,
                'payment_status' => $orderWithRelations->payment_status === 5 ? 'Paid' : 'Unpaid',
                'delivery_date' => $orderWithRelations->delivery_date ? $orderWithRelations->delivery_date->toDateString() : 'Pending Scheduling',
                'created_at' => $orderWithRelations->created_at->toDateTimeString(),
            ];

            return ToolResultDTO::success(
                $call->id,
                $this->key(),
                $trackingInfo,
                ['order_status' => $trackingInfo]
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
