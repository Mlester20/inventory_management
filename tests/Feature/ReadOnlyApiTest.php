<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\GenericName;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use App\Services\InventoryAdjustmentService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The read-only API for other systems: needs a token with the "read" ability, has only GET routes, and returns
 * customers, products with stock per location, sales orders, delivery receipts and invoices.
 */
class ReadOnlyApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Product $product;
    protected SalesOrder $so;

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
        $this->customer = Customer::factory()->create(['customer_name' => 'ALPHA CLINIC']);

        $generic = GenericName::factory()->create(['category_id' => Category::factory()->create()->id, 'generic_name' => 'PARACETAMOL']);
        $this->product = Product::factory()->create(['generic_name_id' => $generic->id, 'item_name' => 'PARACETAMOL 500MG', 'unit_cost' => 7, 'unit_price' => 12]);
        app(InventoryAdjustmentService::class)->createAdjustment([
            'adjustment_date' => now()->toDateString(), 'adjustment_type' => 'opening_balance', 'prepared_by' => $this->admin->id,
            'lines' => [['product_id' => $this->product->id, 'qty' => 40]],
        ], $this->admin->id);

        $this->so = app(SalesOrderService::class)->createSalesOrder([
            'customer_id' => $this->customer->id, 'order_date' => now()->toDateString(), 'prepared_by' => $this->admin->id, 'po_no' => 'PO-9',
            'items' => [['generic_name_id' => $generic->id, 'qty' => 10, 'price' => 12]],
        ]);
    }

    protected function token(string $name = 'Inertia app'): string
    {
        Artisan::call('api:token', ['name' => $name]);

        return trim(collect(explode("\n", trim(Artisan::output())))->last());
    }

    protected function api(string $token): static
    {
        return $this->withToken($token)->withHeader('Accept', 'application/json');
    }

    public function test_no_token_or_a_token_without_read_is_refused(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();

        $writeOnly = User::factory()->create()->createToken('x', ['write'])->plainTextToken;
        $this->api($writeOnly)->getJson('/api/v1/customers')->assertForbidden();

        $this->actingAs($this->admin)->getJson('/api/v1/customers')->assertUnauthorized();   // a web login is not a token
    }

    public function test_a_read_token_lists_and_shows_customers(): void
    {
        $token = $this->token();

        $this->api($token)->getJson('/api/v1/customers?search=alpha')
            ->assertOk()->assertJsonPath('data.0.customer_name', 'ALPHA CLINIC')->assertJsonCount(1, 'data');

        $this->api($token)->getJson('/api/v1/customers/' . $this->customer->id)
            ->assertOk()->assertJsonPath('data.id', $this->customer->id);

        $this->api($token)->getJson('/api/v1/customers?search=nobody')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_nothing_can_be_changed_through_the_api(): void
    {
        $token = $this->token();

        foreach (['postJson' => '/api/v1/customers', 'putJson' => '/api/v1/customers/' . $this->customer->id, 'deleteJson' => '/api/v1/customers/' . $this->customer->id] as $method => $url) {
            $this->api($token)->{$method}($url, ['customer_name' => 'HACKED'])->assertStatus(405);
        }

        $this->assertSame('ALPHA CLINIC', $this->customer->fresh()->customer_name);
    }

    public function test_products_show_stock_per_location_and_hide_the_cost(): void
    {
        $response = $this->api($this->token())->getJson('/api/v1/products?search=paracetamol')->assertOk();

        $row = $response->json('data.0');
        $this->assertSame(40, $row['stock']['total']);
        $this->assertSame(40, $row['stock']['by_location']['Warehouse']);
        $this->assertArrayNotHasKey('unit_cost', $row);
        $this->assertArrayNotHasKey('unit_cost', $row['prices']);
        $this->assertEquals(12, $row['prices']['retail']);

        $this->product->update(['archived_at' => now()]);
        $this->api($this->token())->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');
        $this->api($this->token())->getJson('/api/v1/products/' . $this->product->id)->assertNotFound();
    }

    public function test_sales_orders_come_with_their_lines_and_skip_drafts(): void
    {
        $draft = SalesOrder::create(['customer_id' => $this->customer->id, 'so_no' => 'SO-DRAFT', 'status' => 'open', 'is_draft' => true, 'order_date' => now()->toDateString()]);

        $token = $this->token();
        $response = $this->api($token)->getJson('/api/v1/sales-orders')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame($this->so->so_no, $response->json('data.0.so_no'));
        $this->assertSame('PARACETAMOL', $response->json('data.0.items.0.description'));
        $this->assertSame([10, 0, 10], [
            $response->json('data.0.items.0.qty'), $response->json('data.0.items.0.delivered_qty'), $response->json('data.0.items.0.remaining_qty'),
        ]);

        $this->api($token)->getJson('/api/v1/sales-orders/' . $draft->id)->assertNotFound();
        $this->api($token)->getJson('/api/v1/sales-orders?customer_id=' . ($this->customer->id + 99))->assertOk()->assertJsonCount(0, 'data');
        $this->api($token)->getJson('/api/v1/sales-orders?status=open')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_delivery_receipts_and_invoices(): void
    {
        $soItem = $this->so->items()->first();
        $dr = app(DeliveryReceiptService::class)->createDeliveryReceipt([
            'customer_id' => $this->customer->id, 'sales_order_id' => $this->so->id, 'transaction_type' => 'purchase_order',
            'receipt_date' => now()->toDateString(), 'prepared_by' => $this->admin->id,
            'items' => [['product_batch_id' => $this->product->batches()->first()->id, 'qty' => 4, 'sales_order_item_id' => $soItem->id]],
        ], $this->admin->id);
        $invoice = app(DeliveryReceiptService::class)->createInvoiceFromLines($dr->fresh(), [$dr->items()->first()->id], $this->admin->id);

        $token = $this->token();

        $receipt = $this->api($token)->getJson('/api/v1/delivery-receipts/' . $dr->id)->assertOk()->json('data');
        $this->assertSame([$dr->dr_no, $this->so->so_no, 4], [$receipt['dr_no'], $receipt['sales_order']['so_no'], $receipt['items'][0]['qty']]);
        $this->assertSame(4, $receipt['items'][0]['invoiced_qty']);

        $inv = $this->api($token)->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(1, 'data')->json('data.0');
        $this->assertSame($invoice->sales_no, $inv['sales_no']);
        $this->assertSame($this->customer->customer_name, $inv['customer']['name']);
        $this->assertSame(4, $inv['lines'][0]['qty']);
        $this->assertFalse($inv['cancelled']);
    }

    public function test_updated_since_and_paging(): void
    {
        $other = Customer::factory()->create(['customer_name' => 'BETA CLINIC']);
        $other->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

        $token = $this->token();

        $this->api($token)->getJson('/api/v1/customers?updated_since=' . now()->subDay()->toDateString())->assertOk()->assertJsonCount(1, 'data');
        $this->api($token)->getJson('/api/v1/customers?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
        $this->api($token)->getJson('/api/v1/customers?per_page=5000')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_a_revoked_token_stops_working_and_the_token_account_is_not_a_real_login(): void
    {
        $token = $this->token('Reports tool');

        $account = User::where('email', 'api-reports-tool@integration.local')->firstOrFail();
        $this->assertSame('api', $account->role);
        $account->update(['password' => bcrypt('known-password')]);
        $this->post('/login', ['email' => $account->email, 'password' => 'known-password']);
        $this->assertGuest();

        $this->api($token)->getJson('/api/v1/customers')->assertOk();

        Artisan::call('api:token', ['name' => 'Reports tool', '--revoke' => true]);
        $this->app['auth']->forgetGuards();
        $this->api($token)->getJson('/api/v1/customers')->assertUnauthorized();
    }
}
