<?php

namespace App\Http\Controllers;

use App\Models\PurchaseApproval;
use App\Services\AuditLogger;
use Database\Seeders\ProductSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Operator controls for a repeatable demo. Disabled when demo mode is off.
 */
class DemoController extends Controller
{
    public function resetCatalog(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless((bool) config('trustcart.demo_mode'), 404);

        $invalidated = DB::transaction(function () use ($audit) {
            $count = PurchaseApproval::query()
                ->whereIn('status', ['pending', 'authorized'])
                ->update([
                    'status' => 'invalidated',
                    'invalidated_reason' => 'catalog_reset',
                    'updated_at' => now(),
                ]);

            app(ProductSeeder::class)->run();

            $audit->log('catalog.reset', null, [
                'approvals_invalidated' => $count,
                'source' => 'demo_control',
            ]);

            return $count;
        });

        return back()->with(
            'info',
            "Demo catalog prices restored. {$invalidated} live approval(s) were voided as a result.",
        );
    }
}
