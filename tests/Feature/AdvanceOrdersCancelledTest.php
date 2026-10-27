<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sir's finding: the Advance Orders list still showed the lines of a Cancelled Delivery Receipt.
 * A cancelled DR is void, so its lines are not listed and cannot be invoiced.
 */
class AdvanceOrdersCancelledTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected DeliveryReceipt $dr;

    /** The project's migrations use a MySQL-only fulltext index — see DeliveryReceiptLinkAndBatchPoolingTest. */
    protected function beforeRefreshingDatabase(): void
    {
        $name = (string) config('database.connections.' . config('database.default') . '.database');

        if ($name !== ':memory:' && ! str_ends_with($name, '_test')) {
            throw new \RuntimeException("Refusing to refresh '{$name}': point this test at a scratch database whose name ends in _test.");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->customer = Customer::factory()->create(['customer_name' => 'SAGADA LOCAL GOVERNMENT UNIT']);

        $generic = GenericName::factory()->create(['category_id' => Category::factory()->create()->id]);
        $product = Product::factory()->create(['generic_name_id' => $generic->id]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $product->id, 'qty' => 20]],
        ], $this->admin->id);

        $this->dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'transaction_type' => 'advance_order', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $product->batches()->first()->id, 'qty' => 5]],
        ], $this->admin->id);
    }

    public function test_an_advance_order_dr_is_listed_until_it_is_cancelled(): void
    {
        $this->actingAs($this->admin)->get(route('advance-orders.index'))->assertOk()->assertSee($this->dr->dr_no);

        $this->dr->update(['status' => 'cancelled']);

        $this->actingAs($this->admin)->get(route('advance-orders.index'))->assertOk()->assertDontSee($this->dr->dr_no);
    }

    public function test_a_cancelled_receipt_cannot_be_invoiced_even_by_a_direct_post(): void
    {
        $this->dr->update(['status' => 'cancelled']);
        $dr = $this->dr->fresh('items');

        try {
            app(DeliveryReceiptService::class)->createInvoiceFromLines($dr, $dr->items->pluck('id')->all(), $this->admin->id);
            $this->fail('a cancelled Delivery Receipt was invoiced');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('cancelled', $e->errors()['line_ids'][0]);
        }
    }
}
