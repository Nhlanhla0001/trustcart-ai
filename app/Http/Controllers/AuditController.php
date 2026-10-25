<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\PurchaseApproval;
use App\Models\ShoppingSession;
use App\Support\Requirements;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $uuids = $request->session()->get('trustcart.sessions', []);

        $sessions = ShoppingSession::query()
            ->whereIn('uuid', $uuids)
            ->with(['recommendation.product', 'approvals.orders'])
            ->latest()
            ->get()
            ->map(fn (ShoppingSession $session) => [
                'uuid' => $session->uuid,
                'intent' => $session->raw_intent,
                'created_at' => $session->created_at->toIso8601String(),
                'verdict' => $session->recommendation?->verdict,
                'product' => $session->recommendation?->product?->name,
                'score' => $session->recommendation?->score,
                'approval_status' => $session->approvals->first()?->status,
                'order_status' => $session->approvals->first()?->orders->first()?->status,
            ]);

        return Inertia::render('audit/index', [
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, ShoppingSession $session): Response
    {
        $uuids = $request->session()->get('trustcart.sessions', []);

        abort_unless(in_array($session->uuid, $uuids, true), 404);

        $session->load([
            'recommendation.product',
            'approvals.product',
            'approvals.orders.events',
            'auditEvents' => fn ($query) => $query->latest('id'),
        ]);

        $requirements = Requirements::fromArray($session->requirements);
        $recommendation = $session->recommendation;

        return Inertia::render('audit/show', [
            'session' => [
                'uuid' => $session->uuid,
                'intent' => $session->raw_intent,
                'requirements' => $requirements->toArray(),
                'created_at' => $session->created_at->toIso8601String(),
                'ai_provider' => $session->ai_provider,
                'ai_model' => $session->ai_model,
            ],
            'recommendation' => $recommendation ? [
                'verdict' => $recommendation->verdict,
                'score' => $recommendation->score,
                'narrative' => $recommendation->narrative,
                'narrative_source' => $recommendation->narrative_source,
                'checks' => $recommendation->checks,
                'candidates' => $recommendation->candidates,
                'engine' => $recommendation->engine,
            ] : null,
            'labels' => [
                'features' => array_map(
                    fn (array $feature) => $feature['label'],
                    $requirements->featureLabels(),
                ),
                'minimums' => array_map(
                    fn (array $spec) => $spec['label'].' ≥ '.$spec['value'],
                    $requirements->minSpecLabels(),
                ),
            ],
            'approvals' => $session->approvals->map(fn (PurchaseApproval $approval) => [
                'uuid' => $approval->uuid,
                'status' => $approval->status,
                'product' => $approval->product?->name,
                'total' => $approval->totalLabel(),
                'authorized_at' => $approval->authorized_at?->toIso8601String(),
                'expires_at' => $approval->expires_at->toIso8601String(),
                'invalidated_reason' => $approval->invalidated_reason,
                'orders' => $approval->orders->map(fn ($order) => [
                    'uuid' => $order->uuid,
                    'status' => $order->status,
                    'amount' => $order->amountLabel(),
                    'paypal_order_id' => $order->paypal_order_id,
                    'capture_id' => $order->capture_id,
                    'failure_reason' => $order->failure_reason,
                    'events' => $order->events->map(fn ($event) => [
                        'id' => $event->id,
                        'type' => $event->type,
                        'status' => $event->status,
                        'created_at' => $event->created_at->toIso8601String(),
                    ])->values(),
                ])->values(),
            ])->values(),
            'auditEvents' => $session->auditEvents->map(fn (AuditEvent $event) => [
                'id' => $event->id,
                'type' => $event->type,
                'data' => $event->data,
                'created_at' => $event->created_at->toIso8601String(),
            ])->values(),
        ]);
    }
}
