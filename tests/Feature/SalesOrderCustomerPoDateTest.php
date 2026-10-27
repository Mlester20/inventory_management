<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\GenericName;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's request: besides the Order Date (when the Sales Order was made in the system), a Customer PO
 * Date — the date written on the customer's own Purchase Order.
 */
class SalesOrderCustomerPoDateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected GenericName $generic;

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
        $this->customer = Customer::factory()->create();
        $this->generic = GenericName::factory()->create(['category_id' => Category::factory()->create()->id]);
    }

    protected function payload(array $extra = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id, 'po_no' => 'BOGH 89-26', 'order_date' => '2026-10-27',
            'items' => [['generic_name_id' => $this->generic->id, 'qty' => 2, 'price' => 100, 'advance_order_qty' => 0]],
        ], $extra);
    }

    public function test_the_customer_po_date_is_saved_apart_from_the_order_date(): void
    {
        $this->actingAs($this->admin)->post(route('sales-orders.store'), $this->payload(['customer_po_date' => '2026-10-09']))
            ->assertRedirect();

        $so = SalesOrder::firstOrFail();
        $this->assertSame('2026-10-09', $so->customer_po_date->toDateString());
        $this->assertSame('2026-10-27', $so->order_date->toDateString());
    }

    public function test_it_is_optional(): void
    {
        $this->actingAs($this->admin)->post(route('sales-orders.store'), $this->payload())->assertRedirect();

        $this->assertNull(SalesOrder::firstOrFail()->customer_po_date);
    }

    public function test_a_draft_keeps_it_and_finalizing_does_not_lose_it(): void
    {
        $this->actingAs($this->admin)->post(route('sales-orders.store'), $this->payload(['customer_po_date' => '2026-10-09', 'save_action' => 'draft']));
        $draft = SalesOrder::firstOrFail();
        $this->assertTrue($draft->is_draft);
        $this->assertSame('2026-10-09', $draft->customer_po_date->toDateString());

        $this->actingAs($this->admin)->put(route('sales-orders.update', $draft), $this->payload(['customer_po_date' => '2026-10-10']));
        $this->assertSame('2026-10-10', $draft->fresh()->customer_po_date->toDateString());
        $this->assertFalse($draft->fresh()->is_draft);
    }

    public function test_an_invalid_date_is_refused(): void
    {
        $this->actingAs($this->admin)->post(route('sales-orders.store'), $this->payload(['customer_po_date' => 'not a date']))
            ->assertSessionHasErrors('customer_po_date');

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_the_view_and_the_print_show_it(): void
    {
        $this->actingAs($this->admin)->post(route('sales-orders.store'), $this->payload(['customer_po_date' => '2026-10-09']));
        $so = SalesOrder::firstOrFail();

        $this->actingAs($this->admin)->get(route('sales-orders.show', $so))
            ->assertOk()
            ->assertSee('Customer PO Date')
            ->assertSee('Oct 09, 2026')
            ->assertSee('P.O Date:')
            ->assertSee('10/09/2026');
    }

    public function test_the_print_has_no_po_date_line_when_there_is_none(): void
    {
        $this->actingAs($this->admin)->post(route('sales-orders.store'), $this->payload());

        $this->actingAs($this->admin)->get(route('sales-orders.show', SalesOrder::firstOrFail()))
            ->assertOk()
            ->assertDontSee('P.O Date:');
    }
}
