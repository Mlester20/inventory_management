<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's concern: a draft Delivery Receipt is printed for end-of-day review, and "Deliver to"
 * (customer name and address) came out blank even though the Sales Order was picked.
 */
class DeliveryReceiptDraftDeliverToTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected SalesOrder $salesOrder;

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
        $this->customer = Customer::factory()->create(['customer_name' => 'BONTOC GENERAL HOSPITAL', 'delivery_address' => 'POBLACION, BONTOC']);
        $this->salesOrder = SalesOrder::create([
            'customer_id' => $this->customer->id, 'so_no' => 'SO-DRAFT-1', 'status' => 'open', 'is_draft' => false,
            'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
        ]);
    }

    public function test_a_draft_picked_from_a_sales_order_takes_that_orders_customer(): void
    {
        $draft = app(DeliveryReceiptService::class)->saveDraft([
            'sales_order_id' => $this->salesOrder->id, 'transaction_type' => 'purchase_order', 'receipt_date' => now()->toDateString(),
        ], $this->admin->id);

        $this->assertSame($this->customer->id, $draft->fresh()->customer_id);
    }

    public function test_a_chosen_customer_is_not_overridden(): void
    {
        $other = Customer::factory()->create();

        $draft = app(DeliveryReceiptService::class)->saveDraft([
            'customer_id' => $other->id, 'sales_order_id' => $this->salesOrder->id,
        ], $this->admin->id);

        $this->assertSame($other->id, $draft->fresh()->customer_id);
    }

    public function test_an_old_draft_with_no_customer_still_prints_the_sales_orders_customer(): void
    {
        $draft = DeliveryReceipt::create([
            'dr_no' => 'DR-DRAFT-1', 'sales_order_id' => $this->salesOrder->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id, 'is_draft' => true,
        ]);
        $this->assertNull($draft->customer_id);

        $this->actingAs($this->admin)->get(route('delivery-receipts.show', $draft))
            ->assertOk()
            ->assertSee('BONTOC GENERAL HOSPITAL')
            ->assertSee('POBLACION, BONTOC');
    }
}
