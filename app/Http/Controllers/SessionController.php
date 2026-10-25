<?php

namespace App\Http\Controllers;

use App\Exceptions\ApprovalException;
use App\Models\ShoppingSession;
use App\Services\ApprovalService;
use App\Support\Money;
use App\Support\Requirements;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function show(ShoppingSession $session): Response
    {
        $session->load(['recommendation.product', 'approvals' => fn ($query) => $query->latest()]);

        abort_if($session->recommendation === null, 404);

        $recommendation = $session->recommendation;
        $requirements = Requirements::fromArray($session->requirements);
        $approval = $session->approvals->first();
        $checks = $recommendation->checks;
        $closest = $recommendation->candidates[0] ?? null;
        $checksSubject = 'selected';

        if ($checks === [] && $closest !== null) {
            $checks = $closest['checks'];
            $checksSubject = 'closest';
        }

        return Inertia::render('sessions/show', [
            'session' => [
                'uuid' => $session->uuid,
                'intent' => $session->raw_intent,
                'created_at' => $session->created_at->toIso8601String(),
                'requirements' => $requirements->toArray(),
                'labels' => [
                    'category' => $requirements->categoryLabel(),
                    'budget' => $requirements->budgetLabel(),
                    'currency' => $requirements->currency,
                    'features' => $requirements->featureLabels(),
                    'minimums' => $requirements->minSpecLabels(),
                ],
            ],
            'recommendation' => [
                'verdict' => $recommendation->verdict,
                'score' => $recommendation->score,
                'narrative' => $recommendation->narrative,
                'narrative_source' => $recommendation->narrative_source,
                'checks' => $checks,
                'checks_subject' => $checksSubject,
                'candidates' => $recommendation->candidates,
                'engine' => $recommendation->engine,
                'product' => $recommendation->product?->snapshot(),
            ],
            'approval' => $approval ? [
                'uuid' => $approval->uuid,
                'status' => $approval->status,
                'total' => $approval->totalLabel(),
                'expires_at' => $approval->expires_at->toIso8601String(),
            ] : null,            'checkout' => [
                'paypal_configured' => filled(config('trustcart.paypal.client_id'))
                    && filled(config('trustcart.paypal.client_secret')),
                'environment' => 'sandbox',
            ],
        ]);
    }

    public function approve(ShoppingSession $session, ApprovalService $approvals): RedirectResponse
    {
        $session->load('recommendation');

        if ($session->recommendation === null || ! $session->recommendation->isRecommended()) {
            return redirect()
                ->route('sessions.show', $session)
                ->with('error', 'TrustCart refused this request, so there is nothing to authorize.');
        }

        try {
            $approval = $approvals->createFor($session, $session->recommendation);
        } catch (ApprovalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('approvals.show', $approval);
    }

    public function simulatePriceChange(ShoppingSession $session, ApprovalService $approvals): RedirectResponse
    {
        $session->load('recommendation.product');
        $product = $session->recommendation?->product;

        if ($product === null) {
            return back()->with('error', 'There is no product on this record to reprice.');
        }

        $result = $approvals->simulatePriceChange($product);

        return redirect()
            ->route('sessions.show', $session)
            ->with('info', sprintf(
                'Merchant sync changed %s from %s to %s. %d approval(s) invalidated; the buyer must authorize the new total.',
                $product->name,
                Money::format($result['old_cents'], $product->currency),
                Money::format($result['new_cents'], $product->currency),
                $result['invalidated'],
            ));
    }
}
