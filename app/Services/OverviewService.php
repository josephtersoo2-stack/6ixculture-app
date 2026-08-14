<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Libraries\AppLibrary;
use App\Models\ReturnAndRefund;
use App\Enums\ReturnOrderStatus;
use App\Libraries\QueryExceptionLibrary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OverviewService
{
    public function totalOrders()
    {
        try {
            return Order::where('user_id', Auth::user()->id)->count();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function totalCompletedOrders()
    {
        try {
            return Order::where('user_id', Auth::user()->id)->where('status', OrderStatus::DELIVERED)->count();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function totalReturnedOrders()
    {
        try {
            return ReturnAndRefund::where('user_id', Auth::user()->id)->where('status', ReturnOrderStatus::ACCEPT)->count();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function walletBalance()
    {
        try {
            return AppLibrary::currencyAmountFormat(Auth::user()->balance);
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    public function monnifyVirtualAccount()
    {
        return $this->monnifyVirtualAccountForUser(Auth::user());
    }

    public function monnifyVirtualAccountForUser($user)
    {
        try {
            if (!$user) return null;

            // Return existing stored account if available
            if (!empty($user->monnify_account_number)) {
                return [
                    'bank_name'      => $user->monnify_bank_name ?? 'Wema Bank',
                    'account_number' => $user->monnify_account_number,
                    'account_name'   => $user->monnify_account_name ?? ('6ixculture - ' . $user->name),
                    'accounts'       => [
                        [
                            'bankName'      => $user->monnify_bank_name ?? 'Wema Bank',
                            'accountNumber' => $user->monnify_account_number,
                            'accountName'   => $user->monnify_account_name ?? ('6ixculture - ' . $user->name)
                        ]
                    ]
                ];
            }

            $apiKey       = 'MK_PROD_NDRC85SG6W';
            $secretKey    = 'RW3KT2U9KYE9UH643DNU9K24G6FKHD6K';
            $contractCode = '943975357399';
            $mode         = \App\Enums\GatewayMode::LIVE;

            $gateway = \App\Models\PaymentGateway::with('gatewayOptions')->where('slug', 'monnify')->first();
            if ($gateway) {
                $options = $gateway->gatewayOptions->pluck('value', 'option');
                if (!empty($options['monnify_api_key']))       $apiKey       = $options['monnify_api_key'];
                if (!empty($options['monnify_secret_key']))    $secretKey    = $options['monnify_secret_key'];
                if (!empty($options['monnify_contract_code'])) $contractCode = $options['monnify_contract_code'];
                if (isset($options['monnify_mode']))           $mode         = $options['monnify_mode'];
            }

            $baseUrl = ($mode == \App\Enums\GatewayMode::LIVE) ? 'https://api.monnify.com' : 'https://sandbox.monnify.com';
            $client  = new \GuzzleHttp\Client(['base_uri' => $baseUrl, 'timeout' => 30]);

            Log::info('Monnify Reserved Account: Using base URL: ' . $baseUrl . ' | Mode value: ' . $mode);

            // 1. Get Access Token
            $authHeader = 'Basic ' . base64_encode($apiKey . ':' . $secretKey);
            $authResponse = $client->post('/api/v1/auth/login', [
                'headers' => ['Authorization' => $authHeader, 'Accept' => 'application/json']
            ]);
            $authData = json_decode($authResponse->getBody(), true);
            $accessToken = $authData['responseBody']['accessToken'] ?? null;

            Log::info('Monnify Auth Response: requestSuccessful=' . ($authData['requestSuccessful'] ?? 'null') . ' | hasToken=' . (!empty($accessToken) ? 'yes' : 'no'));

            if ($accessToken) {
                // 2. Call Reserved Account Endpoint
                $accRef = '6IX_USR_' . $user->id;
                $customerEmail = !empty($user->email) ? $user->email : ($user->phone . '@6ixculture.com');
                $payload = [
                    'accountReference'     => $accRef,
                    'accountName'          => '6ixculture - ' . $user->name,
                    'currencyCode'         => 'NGN',
                    'contractCode'         => $contractCode,
                    'customerEmail'        => $customerEmail,
                    'customerName'         => $user->name,
                    'getAllAvailableBanks'  => true
                ];

                Log::info('Monnify Reserved Account Request: ' . json_encode($payload));

                $resResponse = $client->post('/api/v2/bank-transfer/reserved-accounts', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'json' => $payload
                ]);
                $resData = json_decode($resResponse->getBody(), true);

                Log::info('Monnify Reserved Account Response: ' . json_encode($resData));

                if (isset($resData['requestSuccessful']) && $resData['requestSuccessful'] === true) {
                    $accounts = $resData['responseBody']['accounts'] ?? [];
                    if (!empty($accounts)) {
                        $primaryAcc = $accounts[0];
                        $user->monnify_bank_name      = $primaryAcc['bankName'] ?? 'Wema Bank';
                        $user->monnify_account_number = $primaryAcc['accountNumber'];
                        $user->monnify_account_name   = $primaryAcc['accountName'] ?? ('6ixculture - ' . $user->name);
                        $user->save();

                        return [
                            'bank_name'      => $user->monnify_bank_name,
                            'account_number' => $user->monnify_account_number,
                            'account_name'   => $user->monnify_account_name,
                            'accounts'       => $accounts
                        ];
                    }
                }

                // API returned success=false or no accounts
                Log::error('Monnify Reserved Account: API did not return accounts. Response: ' . json_encode($resData));
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'no response';
            Log::error('Monnify Reserved Account ClientException: ' . $e->getMessage() . ' | Response: ' . $responseBody);
        } catch (Exception $exception) {
            Log::error('Monnify Reserved Account Exception: ' . $exception->getMessage());
        }

        // Return null instead of generating fake accounts
        return null;
    }
}
