<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\GenericName;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a report: when a Goods Receipt "Against Purchase
 * Order" line failed to save because its brand hadn't been resolved to a
 * product_id yet, the batch/expiry/qty the encoder had already typed for
 * that same line was silently lost on the validation-failure redisplay,
 * forcing a full re-entry. The redisplay logic (GoodsReceiptController::
 * formData()) skipped restoring a line from old('items') whenever
 * product_id was empty — exactly the one case this exists to recover from.
 * Fixed to key the restore off purchase_order_item_id instead, which is
 * always present for a PO-tab line regardless of whether product_id
 * resolved.
 */
class GoodsReceiptOldInputRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_po_line_submission_preserves_batch_qty_expiry_despite_missing_product_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::factory()->create();
        $generic = GenericName::factory()->create();
        Product::factory()->create(['generic_name_id' => $generic->id]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_no' => 'PO-TEST-0001',
            'status' => 'open',
            'is_draft' => false,
            'order_date' => now(),
        ]);
        $poItem = $po->items()->create([
            'generic_name_id' => $generic->id,
            'qty' => 10,
            'unit_cost' => 6000,
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('goods-receipts.create'))
            ->post(route('goods-receipts.store'), [
                'save_action' => 'posted',
                'receipt_date' => now()->toDateString(),
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'product_id' => '', // the exact failure: brand not resolved
                        'purchase_order_item_id' => (string) $poItem->id,
                        'qty' => '10',
                        'unit_cost' => '6000.00',
                        'batch_no' => 'MYBATCH-123',
                        'expiration_date' => '2027-05-01',
                        'remarks' => 'test remark',
                    ],
                ],
            ]);

        $response->assertSessionHasErrors();

        // Follow the redirect back to the create form and inspect the
        // view data it was actually rendered with.
        $redirect = $this->get($response->headers->get('Location'));
        $poPrefillLines = $redirect->viewData('poPrefillLines');

        $this->assertNotEmpty($poPrefillLines, 'The failed PO line should still be restorable.');
        $line = $poPrefillLines[0];

        $this->assertEquals('10', $line['qty']);
        $this->assertEquals('MYBATCH-123', $line['batch_no']);
        $this->assertEquals('2027-05-01', $line['expiration_date']);
        $this->assertEquals((string) $poItem->id, $line['purchase_order_item_id']);
    }
}
