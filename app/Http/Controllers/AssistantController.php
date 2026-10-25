<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIntentRequest;
use App\Services\DecisionService;
use App\Support\Requirements;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AssistantController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('assistant', [
            'categories' => Requirements::CATEGORIES,
            'features' => collect(Requirements::FEATURES)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'examples' => [
                [
                    'label' => 'The reference request',
                    'category' => 'headphones',
                    'intent' => 'Find wireless headphones under $100 with active noise cancellation and at least 20 hours of battery life.',
                ],
                [
                    'label' => 'Impossible budget',
                    'category' => 'headphones',
                    'intent' => 'I need noise cancelling headphones with 30 hours of battery for under $40.',
                ],
                [
                    'label' => 'Missing evidence',
                    'category' => 'headphones',
                    'intent' => 'Wireless headphones under $100 with noise cancellation and 25 hours of battery — but do not guess.',
                ],
                [
                    'label' => 'Laptop spec',
                    'category' => 'laptops',
                    'intent' => 'A lightweight laptop with a webcam, 16 GB of memory and 512 GB of storage under $1000.',
                ],
            ],
            'ai' => [
                'provider' => config('trustcart.ai.provider'),
                'configured' => filled(config('trustcart.ai.'.config('trustcart.ai.provider').'.key')),
            ],
        ]);
    }

    public function store(StoreIntentRequest $request, DecisionService $decisions): RedirectResponse
    {
        $data = $request->validated();

        $intent = trim((string) ($data['intent'] ?? ''));

        if ($intent === '') {
            $intent = 'Find '.$data['category'];
            if (isset($data['budget'])) {
                $intent .= ' under $'.number_format((float) $data['budget'], 2, '.', '');
            }
            $intent .= '.';
        }

        try {
            $session = $decisions->run(
                $intent,
                $data['category'],
                isset($data['budget']) ? (int) round(((float) $data['budget']) * 100) : null,
            );
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'The decision engine could not complete that request. Nothing was charged. Please try again.');
        }

        $tracked = $request->session()->get('trustcart.sessions', []);
        $tracked[] = $session->uuid;
        $request->session()->put('trustcart.sessions', array_values(array_unique($tracked)));

        return redirect()->route('sessions.show', $session);
    }
}
