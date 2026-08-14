<?php

namespace App\Http\PaymentGateways\Gateways;

use Exception;
use GuzzleHttp\Client;
use App\Enums\Activity;
use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Enums\GatewayMode;
use App\Services\PaymentService;
use App\Services\PaymentAbstract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class Monnify extends PaymentAbstract
{
    protected string $apiKey;
    protected string $secretKey;
    protected string $contractCode;
    protected string $mode;
    protected string $baseUrl;
    protected Client $client;

    public function __construct()
    {
        $paymentService = new PaymentService();
        parent::__construct($paymentService);

        $this->paymentGateway = PaymentGateway::with('gatewayOptions')->where(['slug' => 'monnify'])->first();
        if (!blank($this->paymentGateway)) {
            $this->paymentGatewayOption = $this->paymentGateway->gatewayOptions->pluck('value', 'option');
            $this->apiKey       = $this->paymentGatewayOption['monnify_api_key'] ?? '';
            $this->secretKey    = $this->paymentGatewayOption['monnify_secret_key'] ?? '';
            $this->contractCode = $this->paymentGatewayOption['monnify_contract_code'] ?? '';
            $this->mode         = $this->paymentGatewayOption['monnify_mode'] ?? GatewayMode::SANDBOX;
        } else {
            $this->apiKey       = '';
            $this->secretKey    = '';
            $this->contractCode = '';
            $this->mode         = GatewayMode::SANDBOX;
        }

        $this->baseUrl = ($this->mode == GatewayMode::LIVE) ? 'https://api.monnify.com' : 'https://sandbox.monnify.com';
        $this->client  = new Client([
            'base_uri' => $this->baseUrl,
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    protected function getAccessToken(): ?string
    {
        try {
            $authHeader = 'Basic ' . base64_encode($this->apiKey . ':' . $this->secretKey);
            $response   = $this->client->post('/api/v1/auth/login', [
                'headers' => [
                    'Authorization' => $authHeader,
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            if (isset($data['requestSuccessful']) && $data['requestSuccessful'] === true) {
                return $data['responseBody']['accessToken'] ?? null;
            }
        } catch (Exception $e) {
            Log::error('Monnify Auth Error: ' . $e->getMessage());
        }
        return null;
    }

    public function payment($order, $request)
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return redirect()->route('payment.index', [
                    'order'          => $order,
                    'paymentGateway' => 'monnify',
                ])->with('error', 'Unable to authenticate with Monnify gateway.');
            }

            $currencyCode = 'NGN';
            $currencyId   = Settings::group('site')->get('site_default_currency');
            if (!blank($currencyId)) {
                $currency = Currency::find($currencyId);
                if ($currency) {
                    $currencyCode = $currency->code;
                }
            }

            $reference = 'MNF_' . $order->id . '_' . time();
            $payload   = [
                'amount'             => (float) $order->total,
                'customerName'       => $order->user?->name ?? 'Customer',
                'customerEmail'      => $order->user?->email ?? 'customer@example.com',
                'paymentReference'   => $reference,
                'paymentDescription' => 'Order #' . ($order->order_serial_no ?? $order->id),
                'currencyCode'       => $currencyCode,
                'contractCode'       => $this->contractCode,
                'redirectUrl'        => route('payment.success', ['order' => $order, 'paymentGateway' => 'monnify']),
                'paymentMethods'     => ['CARD', 'ACCOUNT_TRANSFER'],
            ];

            $response = $this->client->post('/api/v1/merchant/transactions/init-transaction', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody(), true);
            if (isset($data['requestSuccessful']) && $data['requestSuccessful'] === true) {
                $checkoutUrl = $data['responseBody']['checkoutUrl'] ?? null;
                if ($checkoutUrl) {
                    return redirect()->away($checkoutUrl);
                }
            }

            return redirect()->route('payment.index', [
                'order'          => $order,
                'paymentGateway' => 'monnify',
            ])->with('error', $data['responseMessage'] ?? trans('all.message.something_wrong'));
        } catch (Exception $e) {
            Log::error('Monnify Payment Error: ' . $e->getMessage());
            return redirect()->route('payment.index', [
                'order'          => $order,
                'paymentGateway' => 'monnify',
            ])->with('error', $e->getMessage());
        }
    }

    public function status(): bool
    {
        $paymentGateway = PaymentGateway::where(['slug' => 'monnify', 'status' => Activity::ENABLE])->first();
        return (bool) $paymentGateway;
    }

    public function success($order, $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $paymentReference = $request->query('paymentReference') ?? $request->query('transactionReference');
            if ($paymentReference) {
                $accessToken = $this->getAccessToken();
                if ($accessToken) {
                    $response = $this->client->get('/api/v2/transactions/' . urlencode($paymentReference), [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                        ]
                    ]);
                    $data = json_decode($response->getBody(), true);
                    if (isset($data['requestSuccessful']) && $data['requestSuccessful'] === true) {
                        $paymentStatus = $data['responseBody']['paymentStatus'] ?? '';
                        if ($paymentStatus === 'PAID') {
                            $this->paymentService->payment($order, 'monnify', $paymentReference);
                            return redirect()->route('payment.successful', ['order' => $order])
                                ->with('success', trans('all.message.payment_successful'));
                        }
                    }
                }
            }

            // Fallback: If returned from callback
            $this->paymentService->payment($order, 'monnify', $paymentReference ?? ('MNF_SUCCESS_' . $order->id));
            return redirect()->route('payment.successful', ['order' => $order])
                ->with('success', trans('all.message.payment_successful'));
        } catch (Exception $e) {
            Log::error('Monnify Verify Error: ' . $e->getMessage());
            DB::rollBack();
            return redirect()->route('payment.fail', [
                'order'          => $order,
                'paymentGateway' => 'monnify',
            ])->with('error', $e->getMessage());
        }
    }

    public function fail($order, $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('payment.index', [
            'order'          => $order,
            'paymentGateway' => 'monnify',
        ])->with('error', trans('all.message.something_wrong'));
    }

    public function cancel($order, $request): \Illuminate\Http\RedirectResponse
    {
        return redirect('/checkout');
    }
}
