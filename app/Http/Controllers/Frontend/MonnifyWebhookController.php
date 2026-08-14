<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Transaction;
use App\Models\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MonnifyWebhookController extends Controller
{
    /**
     * Handle incoming Monnify Webhook notifications.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        $rawPayload = $request->getContent();
        Log::info('Monnify Webhook Received: ', $payload);

        // Fetch Monnify Secret Key for signature verification
        $paymentGateway = PaymentGateway::with('gatewayOptions')->where('slug', 'monnify')->first();
        $secretKey = '';
        if ($paymentGateway && $paymentGateway->gatewayOptions) {
            foreach ($paymentGateway->gatewayOptions as $option) {
                if ($option->option === 'monnify_secret_key') {
                    $secretKey = $option->value;
                }
            }
        }

        // Verify Monnify Signature Header if secret key is present
        $monnifySignature = $request->header('monnify-signature');
        if (!empty($secretKey) && !empty($monnifySignature)) {
            $computedHash = hash_hmac('sha512', $rawPayload, $secretKey);
            if (!hash_equals($computedHash, $monnifySignature)) {
                Log::warning('Monnify Webhook Signature Mismatch!', [
                    'computed' => $computedHash,
                    'header' => $monnifySignature
                ]);
                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }
        }

        $eventType = $request->input('eventType');
        $eventData = $request->input('eventData', []);

        if ($eventType === 'SUCCESSFUL_TRANSACTION' || $request->input('paymentStatus') === 'PAID') {
            $transactionRef = $eventData['transactionReference'] ?? $request->input('transactionReference') ?? ('MNF_TX_' . time());
            $paymentRef     = $eventData['paymentReference'] ?? $request->input('paymentReference') ?? '';
            $amountPaid     = (float) ($eventData['amountPaid'] ?? $request->input('amountPaid') ?? 0);
            $customerEmail  = $eventData['customer']['email'] ?? $request->input('customer.email') ?? '';
            $productRef     = $eventData['product']['reference'] ?? '';

            Log::info("Monnify Webhook Processing: TX: {$transactionRef}, PaymentRef: {$paymentRef}, Amount: {$amountPaid}, Email: {$customerEmail}");

            // 1. Try matching Order by order_serial_no or paymentRef
            $order = null;
            if (!empty($paymentRef)) {
                $order = Order::where('order_serial_no', $paymentRef)->first();
            }
            if (!$order && !empty($productRef)) {
                $order = Order::where('order_serial_no', $productRef)->first();
            }
            if (!$order && preg_match('/MNF_SUCCESS_(\d+)/i', $paymentRef, $matches)) {
                $order = Order::find($matches[1]);
            }
            if (!$order && is_numeric($paymentRef)) {
                $order = Order::find($paymentRef);
            }

            if ($order) {
                if ($order->payment_status !== PaymentStatus::PAID) {
                    try {
                        $paymentService = new PaymentService();
                        $paymentService->payment($order, 'monnify', $transactionRef);
                        Log::info("Monnify Webhook: Order #{$order->order_serial_no} (ID: {$order->id}) updated to PAID successfully.");
                    } catch (\Exception $e) {
                        Log::error("Monnify Webhook Order Update Error: " . $e->getMessage());
                    }
                } else {
                    Log::info("Monnify Webhook: Order #{$order->order_serial_no} was already marked as PAID.");
                }
                return response()->json(['status' => 'success', 'message' => 'Order payment verified.'], 200);
            }

            // 2. Dedicated Virtual Account Deposit / Wallet Top-Up
            $accountNumber = $eventData['destinationAccountInformation']['accountNumber'] ?? null;
            $user = null;
            if (!empty($accountNumber)) {
                $user = User::where('monnify_account_number', $accountNumber)->first();
            }
            if (!$user && !empty($customerEmail)) {
                $user = User::where('email', $customerEmail)->first();
            }

            if ($user) {
                // Prevent duplicate processing
                $alreadyProcessed = Transaction::where('transaction_no', $transactionRef)->exists();
                if (!$alreadyProcessed) {
                    DB::transaction(function () use ($user, $amountPaid, $transactionRef) {
                        $user->balance = (float) $user->balance + $amountPaid;
                        $user->save();

                        Transaction::create([
                            'order_id'       => 0,
                            'transaction_no' => $transactionRef,
                            'amount'         => $amountPaid,
                            'payment_method' => 'monnify',
                            'sign'           => '+',
                            'type'           => 'wallet'
                        ]);
                    });
                    Log::info("Monnify Webhook: User {$user->email} wallet credited with ₦{$amountPaid}. New balance: {$user->balance}");
                } else {
                    Log::info("Monnify Webhook: Transaction {$transactionRef} already credited to user {$user->email}.");
                }
                return response()->json(['status' => 'success', 'message' => 'Wallet credited successfully.'], 200);
            }

            Log::warning("Monnify Webhook: No matching order or user found for PaymentRef: {$paymentRef}, Email: {$customerEmail}");
        }

        return response()->json(['status' => 'success', 'message' => 'Event received'], 200);
    }
}
