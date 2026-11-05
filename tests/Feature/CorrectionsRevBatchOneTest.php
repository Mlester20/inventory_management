<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\GenericName;
use App\Models\InventoryAdjustment;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's CORRECTIONS_REV ticket, the parts that need no clarification: the three new Stock Out adjustment types,
 * the full item description in the general search, the Sales Order status filter, and the Sales Order page
 * (line numbers, sort, item description under the generic, packing list for a draft).
 */
class CorrectionsRevBatchOneTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;

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
    }

    protected function product(string $generic, string $brand, string $description, int $stock = 0): Product
    {
        $category = Category::first() ?? Category::factory()->create();
        $genericName = GenericName::firstOrCreate(['generic_name' => $generic, 'unit' => 'BOT'], ['category_id' => $category->id]);
        $product = Product::factory()->create(['generic_name_id' => $genericName->id, 'brand_name' => $brand, 'description' => $description]);

        if ($stock > 0) {
            app(InventoryAdjustmentService::class)->createAdjustment([
                'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
                'lines' => [['product_id' => $product->id, 'qty' => $stock]],
            ], $this->admin->id);
        }

        return $product;
    }

    protected function order(array $lines, bool $draft = false, string $status = 'open'): SalesOrder
    {
        $order = SalesOrder::create([
            'customer_id' => $this->customer->id, 'so_no' => 'SO-CR-' . (SalesOrder::count() + 1), 'status' => $status, 'is_draft' => $draft,
            'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
        ]);

        foreach ($lines as [$genericNameId, $productId]) {
            $order->items()->create(['generic_name_id' => $genericNameId, 'product_id' => $productId, 'qty' => 5, 'price' => 10, 'delivered_qty' => 0]);
        }

        return $order;
    }

    public function test_the_three_new_stock_out_types_exist_and_take_stock_out(): void
    {
        foreach (['stock_out_office_use' => 'Office Use', 'stock_out_donation' => 'Donation', 'stock_out_production' => 'Production'] as $type => $label) {
            $this->assertSame("Stock Out - {$label}", InventoryAdjustment::TYPES[$type]);

            $product = $this->product('ITEM ' . $label, 'B', 'ITEM ' . $label . ', 5CM', 10);
            $adjustment = app(InventoryAdjustmentService::class)->createAdjustment([
                'adjustment_date' => now()->toDateString(), 'adjustment_type' => $type, 'prepared_by' => $this->admin->id,
                'lines' => [['product_id' => $product->id, 'product_batch_id' => $product->batches()->first()->id, 'qty' => 4]],
            ], $this->admin->id);

            $this->assertSame('out', $adjustment->direction());
            $this->assertSame(6, $product->batches()->first()->qtyAtLocation(Location::warehouse()->id), "{$type} did not take 4 out");
        }

        $this->actingAs($this->admin)->get(route('inventory-adjustments.create'))
            ->assertOk()->assertSee('Stock Out - Office Use')->assertSee('Stock Out - Donation')->assertSee('Stock Out - Production');
    }

    public function test_the_general_search_shows_the_full_item_description(): void
    {
        $this->product('NSS', 'MEDIC', 'NSS, 1LI (MEDIC)', 1);
        $this->product('NSS', 'MEDIC', 'NSS, 500ML (MEDIC)', 0);

        // two letters search with LIKE (a fulltext index only sees committed rows, and a test runs inside one transaction)
        $titles = collect($this->actingAs($this->admin)->getJson('/api/search?q=ns')->assertOk()->json('results.items'))->pluck('title')->all();

        $this->assertContains('NSS, 1LI (MEDIC)', $titles);
        $this->assertContains('NSS, 500ML (MEDIC)', $titles);
    }

    public function test_the_sales_order_list_filters_by_status(): void
    {
        $draft = $this->order([], true);
        $open = $this->order([]);
        $completed = $this->order([], false, 'completed');

        $see = fn (string $query) => $this->actingAs($this->admin)->get(route('sales-orders.index') . $query)->assertOk();

        $see('?status=draft')->assertSee($draft->so_no)->assertDontSee($open->so_no)->assertDontSee($completed->so_no);
        $see('?status=open')->assertSee($open->so_no)->assertDontSee($draft->so_no)->assertDontSee($completed->so_no);
        $see('?status=completed')->assertSee($completed->so_no)->assertDontSee($open->so_no);
        $see('')->assertSee($draft->so_no)->assertSee($open->so_no)->assertSee($completed->so_no);
        $see('?status=nonsense')->assertSee($open->so_no)->assertSee($completed->so_no);
    }

    public function test_the_order_page_numbers_the_lines_and_sorts_by_generic_description(): void
    {
        $zinc = $this->product('ZINC', 'Z', 'ZINC TAB');
        $amox = $this->product('AMOXICILLIN', 'A', 'AMOX CAP');
        $order = $this->order([[$zinc->generic_name_id, null], [$amox->generic_name_id, null]]);

        $original = $this->actingAs($this->admin)->get(route('sales-orders.show', $order))->assertOk()->assertSee('Sort by Generic Description');
        $text = preg_replace('/\s+/', ' ', strip_tags($original->getContent()));
        $this->assertLessThan(strpos($text, 'AMOXICILLIN'), strpos($text, 'ZINC'), 'the entry order keeps ZINC first');

        $sorted = $this->actingAs($this->admin)->get(route('sales-orders.show', ['sales_order' => $order->id, 'sort' => 'generic']))
            ->assertOk()->assertSee('Original order');
        $text = preg_replace('/\s+/', ' ', strip_tags($sorted->getContent()));
        $this->assertLessThan(strpos($text, 'ZINC'), strpos($text, 'AMOXICILLIN'), 'sorted A-Z puts AMOXICILLIN first');
    }

    public function test_the_prints_show_the_item_description_under_the_generic_when_there_is_one(): void
    {
        $identified = $this->product('DISINFECTANT', 'ZONROX', 'DISINFECTANT, GAL (ZONROX)');
        $unidentified = $this->product('ALCOHOL', 'X', 'ALCOHOL 70%');
        $order = $this->order([[$identified->generic_name_id, $identified->id], [$unidentified->generic_name_id, null]]);

        $html = $this->actingAs($this->admin)->get(route('sales-orders.show', $order))->assertOk()->getContent();

        // once in the screen table, once in the standard print sheet, once in the packing list sheet
        $this->assertSame(3, substr_count($html, 'DISINFECTANT, GAL (ZONROX)'));
        $this->assertSame(0, substr_count($html, 'ALCOHOL 70%'), 'a line not matched to a product shows no item description');
    }

    public function test_a_draft_order_can_print_the_packing_list(): void
    {
        $product = $this->product('DISINFECTANT', 'ZONROX', 'DISINFECTANT, GAL (ZONROX)');
        $draft = $this->order([[$product->generic_name_id, $product->id]], true);
        $open = $this->order([[$product->generic_name_id, $product->id]]);

        $this->actingAs($this->admin)->get(route('sales-orders.show', $draft))->assertOk()
            ->assertSee('Print Packing List')->assertSee('Sales Order List');
        $this->actingAs($this->admin)->get(route('sales-orders.show', $open))->assertOk()->assertDontSee('Print Packing List');
    }
}
