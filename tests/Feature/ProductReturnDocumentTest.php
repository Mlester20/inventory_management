<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's flow: Product Return > New Product Return > customer > DR (every item appears) > Return Qty and a reason
 * per line > Save. The document holds only the lines that came back, has its own number and can be printed.
 */
class ProductReturnDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Product $scale;
    protected Product $board;
    protected Product $meter;
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
        $this->customer = Customer::factory()->create(['customer_name' => 'SAGADA LOCAL GOVERNMENT UNIT', 'delivery_address' => 'PATAY, SAGADA']);

        $category = Category::factory()->create();
        $make = function (string $description) use ($category) {
            $generic = GenericName::factory()->create(['category_id' => $category->id]);
            $product = Product::factory()->create(['generic_name_id' => $generic->id, 'description' => $description, 'unit_price' => 100]);
            app(InventoryAdjustmentService::class)->createAdjustment([
                'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
                'lines' => [['product_id' => $product->id, 'qty' => 50]],
            ], $this->admin->id);

            return $product;
        };
        $this->scale = $make('WEIGHING SCALE - DIGITAL (SECA 813)');
        $this->board = $make('WOODEN HEIGHT BOARD - FOLDABLE, 200CM');
        $this->meter = $make('STADIOMETER/HEIGHT METER (SECA 213)');

        $this->dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'transaction_type' => 'walk_in', 'receipt_date' => now()->toDateString(),
            'prepared_by' => $this->admin->id,
            'items' => [
                ['product_batch_id' => $this->scale->batches()->first()->id, 'qty' => 19],
                ['product_batch_id' => $this->board->batches()->first()->id, 'qty' => 19],
                ['product_batch_id' => $this->meter->batches()->first()->id, 'qty' => 5],
            ],
        ], $this->admin->id);
    }

    protected function qty(Product $product): int
    {
        return (int) $product->batches()->first()->fresh()->qtyAtLocation(Location::warehouse()->id);
    }

    protected function save(array $lines)
    {
        return $this->actingAs($this->admin)->post(route('return-items.store-from-dr'), [
            'delivery_receipt_id' => $this->dr->id, 'return_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'lines' => $lines,
        ]);
    }

    protected function twoLines(): array
    {
        return [
            $this->board->batches()->first()->id => ['qty' => 2, 'reason' => 'Damaged During Delivery'],
            $this->meter->batches()->first()->id => ['qty' => 1, 'reason' => 'Wrong Item Received'],
            $this->scale->batches()->first()->id => ['qty' => 0, 'reason' => ''],
        ];
    }

    public function test_saving_makes_one_numbered_document_with_only_the_returned_lines(): void
    {
        $this->save($this->twoLines())->assertRedirect();

        $document = ProductReturn::firstOrFail();
        $this->assertMatchesRegularExpression('/^PR-\d{4}-00001$/', $document->return_no);
        $this->assertSame($this->dr->id, $document->delivery_receipt_id);
        $this->assertSame($this->customer->id, $document->customer_id);
        $this->assertCount(2, $document->items, 'the scale (qty 0) is not on the document');
        $this->assertSame(['Damaged During Delivery', 'Wrong Item Received'], $document->items->pluck('reason')->all());
        $this->assertSame($document->return_no, $document->items->first()->reference);
    }

    public function test_the_numbers_run_on(): void
    {
        $this->save($this->twoLines());
        $this->save([$this->scale->batches()->first()->id => ['qty' => 1, 'reason' => 'Changed Mind']]);

        $this->assertSame(['PR-' . now()->year . '-00001', 'PR-' . now()->year . '-00002'], ProductReturn::orderBy('id')->pluck('return_no')->all());
    }

    public function test_the_document_page_lists_the_lines_and_prints_with_the_letterhead(): void
    {
        $this->save($this->twoLines());
        $document = ProductReturn::firstOrFail();

        $this->actingAs($this->admin)->get(route('product-returns.show', $document))
            ->assertOk()
            ->assertSee($document->return_no)
            ->assertSee('WOODEN HEIGHT BOARD - FOLDABLE, 200CM')
            ->assertSee('STADIOMETER/HEIGHT METER (SECA 213)')
            ->assertDontSee('WEIGHING SCALE - DIGITAL (SECA 813)')
            ->assertSee('PRODUCT RETURN')
            ->assertSee('P.R No.')
            ->assertSee($this->dr->dr_no)
            ->assertSee('PATAY, SAGADA')
            ->assertSee('Damaged During Delivery')
            ->assertSee('Wrong Item Received')
            ->assertSee('Print');
    }

    public function test_approving_the_document_puts_every_line_back_with_no_refund_when_not_invoiced(): void
    {
        $this->save($this->twoLines());
        $document = ProductReturn::firstOrFail();
        $this->assertSame([31, 45], [$this->qty($this->board), $this->qty($this->meter)]);

        $this->actingAs($this->admin)->post(route('product-returns.approve', $document), ['refund_method' => 'cash'])
            ->assertRedirect(route('product-returns.show', $document));

        $document = $document->fresh('items');
        $this->assertSame('approved', $document->status());
        $this->assertTrue($document->items->every(fn (ReturnItem $item) => $item->refund_method === 'none' && (float) $item->refund_amount === 0.0));
        // The damaged board is restocked and written off straight away (net 31); the wrong-item meter is sellable again (45 + 1).
        $this->assertSame([31, 46], [$this->qty($this->board), $this->qty($this->meter)]);
        $this->assertSame(0, $this->customer->payments()->count());
    }

    public function test_each_line_can_be_sellable_or_written_off(): void
    {
        $this->save($this->twoLines());
        $document = ProductReturn::firstOrFail();
        [$boardLine, $meterLine] = [$document->items[0], $document->items[1]];

        $this->actingAs($this->admin)->post(route('product-returns.approve', $document), [
            'disposition' => [$boardLine->id => 'write_off', $meterLine->id => 'sellable'],
        ]);

        $this->assertSame('write_off', $boardLine->fresh()->stock_disposition);
        $this->assertSame('sellable', $meterLine->fresh()->stock_disposition);
        $this->assertSame(46, $this->qty($this->meter), 'the sellable meter is back: 50 - 5 + 1');
    }

    public function test_rejecting_the_document_returns_nothing_to_stock(): void
    {
        $this->save($this->twoLines());
        $document = ProductReturn::firstOrFail();

        $this->actingAs($this->admin)->post(route('product-returns.reject', $document), ['rejection_reason' => 'not our goods']);

        $this->assertSame('rejected', $document->fresh('items')->status());
        $this->assertSame([31, 45], [$this->qty($this->board), $this->qty($this->meter)]);
    }

    public function test_only_a_full_admin_can_approve(): void
    {
        $this->save($this->twoLines());
        $document = ProductReturn::firstOrFail();

        $this->actingAs(User::factory()->create(['role' => 'admin_staff', 'is_active' => true]))
            ->post(route('product-returns.approve', $document));

        $this->assertSame('pending', $document->fresh('items')->status());
    }

    public function test_the_list_and_product_history_point_at_the_document(): void
    {
        $this->save($this->twoLines());
        $document = ProductReturn::firstOrFail();
        $this->actingAs($this->admin)->post(route('product-returns.approve', $document));

        $this->actingAs($this->admin)->get(route('return-items.index'))
            ->assertOk()->assertSee($document->return_no)->assertSee('New Product Return');

        $this->actingAs($this->admin)->get(route('inventory-items.index', ['tab' => 'history', 'product_id' => $this->board->id]))
            ->assertOk()->assertSee($document->return_no)->assertSee(route('product-returns.show', $document), false);
    }

    public function test_the_dr_and_so_note_says_how_many_units_came_back(): void
    {
        $this->save($this->twoLines());
        $this->actingAs($this->admin)->post(route('product-returns.approve', ProductReturn::firstOrFail()));

        $this->actingAs($this->admin)->get(route('delivery-receipts.show', $this->dr))
            ->assertOk()
            ->assertSee('2 units Returned by Client')
            ->assertSee('1 unit Returned by Client');
    }
}
