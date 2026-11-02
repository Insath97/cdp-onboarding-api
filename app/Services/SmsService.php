<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $baseUrl;

    protected string $sendSmsUrl;

    protected ?string $username;

    protected ?string $password;

    protected ?string $mask;

    public function __construct()
    {
        $this->baseUrl = config('services.dialog_sms.url', 'https://esms.dialog.lk');
        // Note: Send SMS endpoint specifically uses e-sms.dialog.lk as per documentation
        $this->sendSmsUrl = config('services.dialog_sms.send_url', 'https://e-sms.dialog.lk/api/v2/sms');
        $this->username = config('services.dialog_sms.username');
        $this->password = config('services.dialog_sms.password');
        $this->mask = config('services.dialog_sms.mask');
    }

    /**
     * Get access token from Dialog API.
     */
    private function getAccessToken(): ?string
    {
        // Check if token exists in cache
        if (Cache::has('dialog_sms_token')) {
            return Cache::get('dialog_sms_token');
        }

        try {
            $response = Http::post($this->baseUrl.'/api/v2/user/login', [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'success') {
                    // Store token in cache as per API doc expiration (fallback 12 hours)
                    Cache::put('dialog_sms_token', $data['token'], now()->addSeconds($data['expiration'] ?? 43200));

                    Log::info('Dialog SMS Token Generated Successfully');

                    return $data['token'];
                }
            }

            Log::error('Failed to get Dialog SMS Token', [
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $th) {
            Log::error('Dialog SMS Token Error: '.$th->getMessage());

            return null;
        }
    }

    /**
     * Send SMS via Dialog Gateway.
     *
     * @param  string|array  $numbers  Single number or array of numbers
     * @param  int  $paymentMethod  0=wallet, 4=package
     */
    public function sendSms($numbers, string $message, int $paymentMethod = 0): bool
    {
        try {
            // Get access token
            $token = $this->getAccessToken();
            if (! $token) {
                Log::error('Cannot send SMS: No valid access token');

                return false;
            }

            // Format numbers
            $numbers = is_array($numbers) ? $numbers : [$numbers];
            $formattedNumbers = [];

            foreach ($numbers as $number) {
                $formattedNumbers[] = [
                    'mobile' => $this->formatNumber($number),
                ];
            }

            // Generate unique transaction ID (1-18 digits as per API spec)
            $transactionId = time().rand(100, 999);

            // Prepare request according to API documentation (v2)
            $payload = [
                'msisdn' => $formattedNumbers,
                'sourceAddress' => $this->mask,
                'message' => $message,
                'transaction_id' => $transactionId,
                'payment_method' => $paymentMethod,
            ];

            Log::info('Sending SMS via Dialog API', [
                'numbers_count' => count($formattedNumbers),
                'transaction_id' => $transactionId,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->post($this->sendSmsUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'success') {
                    Log::info('SMS Sent Successfully', [
                        'campaign_id' => $data['data']['campaignId'] ?? null,
                        'campaign_cost' => $data['data']['campaignCost'] ?? null,
                        'wallet_balance' => $data['data']['walletBalance'] ?? null,
                        'transaction_id' => $transactionId,
                    ]);

                    return true;
                }
            }

            // Log the error response
            Log::error('SMS API Error Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'errCode' => $response->json('errCode') ?? 'Unknown',
                'transaction_id' => $transactionId,
            ]);

            return false;
        } catch (\Throwable $th) {
            Log::error('SMS Service Error: '.$th->getMessage(), [
                'trace' => $th->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Check campaign status by transaction ID.
     */
    public function checkCampaignStatus(string $transactionId): ?array
    {
        try {
            $token = $this->getAccessToken();
            if (! $token) {
                return null;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/api/v2/sms/check-transaction', [
                'transaction_id' => $transactionId,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Throwable $th) {
            Log::error('Check Campaign Status Error: '.$th->getMessage());

            return null;
        }
    }

    /**
     * Get account balance (for GET request users).
     */
    public function getBalance(string $esmsqk): ?float
    {
        try {
            $response = Http::get($this->baseUrl.'/api/v1/message-via-url/check/balance', [
                'esmsqk' => $esmsqk,
            ]);

            if ($response->successful()) {
                $body = $response->body();
                $parts = explode('|', $body);

                if ($parts[0] == 1) {
                    return floatval($parts[1]);
                }
            }

            return null;
        } catch (\Throwable $th) {
            Log::error('Get Balance Error: '.$th->getMessage());

            return null;
        }
    }

    /**
     * Format phone number to required format (7XXXXXXXX - 9 digits).
     */
    protected function formatNumber(string $number): string
    {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // If starts with 94, remove it
        if (str_starts_with($number, '94')) {
            $number = substr($number, 2);
        }

        // If starts with 0, remove it
        if (str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }

        // Ensure it's exactly 9 digits as per API docs
        if (strlen($number) > 9) {
            // Keep last 9 digits if longer (might happen if someone enters 07XXXXXXXX)
            $number = substr($number, -9);
        }

        return $number;
    }

    /**
     * Send SMS to multiple recipients.
     *
     * @return array<string, bool>
     */
    public function sendBulkSms(array $numbers, string $message, int $paymentMethod = 0): array
    {
        // Dialog API supports multiple numbers in one request
        // So we can send all at once
        $success = $this->sendSms($numbers, $message, $paymentMethod);

        $results = [];
        foreach ($numbers as $number) {
            $results[$number] = $success;
        }

        return $results;
    }
}
