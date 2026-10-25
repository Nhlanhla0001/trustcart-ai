<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\EvaluationEngine;
use App\Support\Money;
use App\Support\Requirements;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(EvaluationEngine $engine): Response
    {
        $requirements = Requirements::fromArray([
            'category' => 'headphones',
            'budget_cents' => 10000,
            'currency' => 'USD',
            'required_features' => ['noise_cancellation', 'wireless'],
            'min_specs' => ['battery_hours' => 20],
        ]);

        $products = Product::query()->active()->where('category', 'headphones')->get();

        $evaluations = $products
            ->map(fn (Product $product) => $engine->evaluate($product, $requirements))
            ->all();

        $qualified = collect($evaluations)->where('verdict', 'qualified')->sortByDesc('score')->first();

        $rejected = collect($evaluations)
            ->where('verdict', 'rejected')
            ->sortBy('product.price_cents')
            ->first();

        return Inertia::render('welcome', [
            'catalogSize' => Product::query()->active()->count(),
            'specimen' => [
                'requirements' => [
                    'summary' => 'Wireless headphones · under $100 · noise cancellation · 20 h battery',
                    'budget' => Money::format(10000, 'USD'),
                ],
                'qualified' => $qualified ? [
                    'name' => $qualified['product']['name'],
                    'price' => $qualified['product']['price'],
                    'merchant' => $qualified['product']['merchant'],
                    'score' => $qualified['score'],
                    'checks' => array_slice($qualified['checks'], 0, 4),
                ] : null,
                'cheaper' => $rejected ? [
                    'name' => $rejected['product']['name'],
                    'price' => $rejected['product']['price'],
                    'reason' => $rejected['reason'],
                    'checks' => array_slice(array_values(array_filter(
                        $rejected['checks'],
                        fn (array $check) => $check['result'] !== 'pass',
                    )), 0, 3),
                ] : null,
                'qualified_count' => collect($evaluations)->where('verdict', 'qualified')->count(),
                'rejected_count' => collect($evaluations)->where('verdict', 'rejected')->count(),
                'unverified_count' => collect($evaluations)->where('verdict', 'unverified')->count(),
            ],
        ]);
    }
}
