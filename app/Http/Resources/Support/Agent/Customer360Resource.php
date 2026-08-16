<?php

namespace App\Http\Resources\Support\Agent;

use App\Models\Order;
use App\Support\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Customer360Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [
                'is_guest' => true,
                'name' => 'Guest User',
                'email' => null,
                'phone' => null,
                'total_orders' => 0,
                'total_spend' => '₦0.00',
                'recent_orders' => [],
                'open_tickets_count' => 0,
                'member_since' => null,
            ];
        }

        $orders = Order::where('user_id', $this->id)->latest('id')->limit(5)->get();
        $totalOrders = Order::where('user_id', $this->id)->count();
        $totalSpend = Order::where('user_id', $this->id)->sum('total');
        $openTicketsCount = SupportTicket::where('customer_id', $this->id)->whereNotIn('status', ['resolved', 'closed'])->count();

        return [
            'id' => $this->id,
            'is_guest' => false,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'username' => $this->username,
            'total_orders' => $totalOrders,
            'total_spend' => '₦' . number_format($totalSpend, 2),
            'open_tickets_count' => $openTicketsCount,
            'member_since' => $this->created_at?->format('M d, Y'),
            'recent_orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_serial_no' => $order->order_serial_no,
                    'status' => $order->status,
                    'total' => '₦' . number_format($order->total, 2),
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at?->format('M d, Y H:i'),
                ];
            }),
        ];
    }
}
