<?php

namespace App\Services\PayPal;

use App\Exceptions\PayPalException;
use App\Exceptions\PayPalNotConfigured;
use App\Models\PurchaseApproval;
use App\Support\Money;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayPal Orders v2, sandbox only. The client secret stays on the server, every
 * order is created from a stored approval amount, and no response is treated
 * as a completed payment until PayPal reports COMPLETED.
 */
class PayPalClient
{
    public function isConfigured(): bool
    {
        return filled(config('trustcart.paypal.client_id'))
            && filled(config('trustcart.paypal.client_secret'));
    }

    public function isSandbox(): bool
    {
        return str_contains((string) config('trustcart.paypal.base_url'), 'sandbox');
    }

    /**
     * @return array<string, mixed>
     */
    public function createOrder(PurchaseApproval $approval, string $returnUrl, string $cancelUrl): array
    {
        $this->assertReady();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'approval-'.$approval->uuid,
                'custom_id' => $approval->uuid,
                'description' => 'TrustCart AI sandbox order — synthetic demo catalog',
                'amount' => [
                    'currency_code' => $approval->currency,
                    'value' => Money::toDecimal($approval->total_cents),
                ],
            ]],
            'application_context' => [
                'brand_name' => 'TrustCart AI',
                'landing_page' => 'LOGIN',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        return $this->request('post', '/v2/checkout/orders', $payload, $this->requestId('create', $approval->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $paypalOrderId): array
    {
        $this->assertReady();

        return $this->request('get', '/v2/checkout/orders/'.rawurlencode($paypalOrderId));
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $paypalOrderId, string $requestId): array
    {
        $this->assertReady();

        return $this->request('post', '/v2/checkout/orders/'.rawurlencode($paypalOrderId).'/capture', [], $requestId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = [], ?string $requestId = null): array
    {
        $url = rtrim((string) config('trustcart.paypal.base_url'), '/').$path;

        $request = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('trustcart.paypal.timeout'));

        if ($requestId !== null) {
            $request = $request->withHeaders(['PayPal-Request-Id' => $requestId]);
        }

        try {
            $response = $method === 'get' ? $request->get($url) : $request->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('PayPal transport failure', ['path' => $path, 'message' => $e->getMessage()]);

            throw new PayPalException('PayPal could not be reached. No payment was taken.');
        }

        if ($response->failed()) {
            $this->throwFailure($response, $path);
        }

        return $response->json() ?? [];
    }

    private function accessToken(): string
    {
        $cacheKey = 'paypal.token.'.substr(md5((string) config('trustcart.paypal.client_id')), 0, 12);

        return Cache::remember($cacheKey, 300, function () {
            $response = Http::asForm()
                ->withBasicAuth(
                    (string) config('trustcart.paypal.client_id'),
                    (string) config('trustcart.paypal.client_secret'),
                )
                ->timeout((int) config('trustcart.paypal.timeout'))
                ->post(rtrim((string) config('trustcart.paypal.base_url'), '/').'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed() || ! filled($response->json('access_token'))) {
                $debugId = $response->json('error_description') ?? null;
                Log::error('PayPal authentication failed', [
                    'status' => $response->status(),
                    'error' => $debugId,
                ]);

                throw new PayPalException('PayPal rejected the sandbox credentials.', is_string($debugId) ? $debugId : null);
            }

            return (string) $response->json('access_token');
        });
    }

    private function throwFailure(Response $response, string $path): never
    {
        $body = $response->json() ?? [];
        $issue = data_get($body, 'details.0.issue');
        $description = data_get($body, 'details.0.description')
            ?? data_get($body, 'message')
            ?? $response->reason();
        $debugId = data_get($body, 'debug_id');

        Log::error('PayPal request failed', [
            'path' => $path,
            'status' => $response->status(),
            'issue' => $issue,
            'description' => $description,
            'debug_id' => $debugId,
        ]);

        $message = match (true) {
            $issue === 'INSTRUMENT_DECLINED' => 'PayPal declined the selected funding source. No payment was taken.',
            $issue === 'ORDER_ALREADY_CAPTURED' => 'This PayPal order has already been captured.',
            in_array($issue, ['ORDER_NOT_APPROVED', 'PAYER_ACTION_REQUIRED'], true) => 'The buyer has not approved this PayPal order yet.',
            $response->status() >= 500 => 'PayPal is temporarily unavailable. No payment was taken.',
            default => 'PayPal rejected the request. No payment was taken.',
        };

        throw new PayPalException($message, is_string($debugId) ? $debugId : null, $response->status());
    }

    private function assertReady(): void
    {
        if (! $this->isConfigured()) {
            throw new PayPalNotConfigured;
        }

        if (! $this->isSandbox() && ! config('trustcart.paypal.allow_live', false)) {
            throw new PayPalException('Live PayPal is disabled in this prototype. Point PAYPAL_BASE_URL at the sandbox.');
        }
    }

    private function requestId(string $action, string $approvalUuid): string
    {
        return 'tc-'.substr(hash('sha256', $action.'|'.$approvalUuid), 0, 32);
    }
}
