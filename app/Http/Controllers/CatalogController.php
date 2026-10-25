<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Requirements;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __invoke(): Response
    {
        $products = Product::query()
            ->active()
            ->orderBy('category')
            ->orderBy('price_cents')
            ->get()
            ->map(function (Product $product) {
                $snapshot = $product->snapshot();
                $snapshot['integrity_flag'] = $this->looksInjected($product->description);
                $snapshot['description'] = $product->description;

                return $snapshot;
            });

        return Inertia::render('catalog/index', [
            'products' => $products,
            'lastSynced' => Product::query()->max('last_synced_at'),
            'featureLabels' => Requirements::FEATURES,
            'minSpecLabels' => collect(Requirements::MIN_SPECS)
                ->map(fn (array $meta) => $meta['label'])
                ->merge(['driver_mm' => 'Driver'])
                ->all(),
        ]);
    }

    /**
     * Surface instruction-like text in synthetic listings. The text is shown
     * to the operator and never sent to the model.
     */
    private function looksInjected(string $description): bool
    {
        return (bool) preg_match(
            '/(ignore (all )?(previous|prior) instructions|system override|set the total|disregard .{0,20}rules)/i',
            $description,
        );
    }
}
