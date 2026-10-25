<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sir's request: names (Encoders) that can be picked under "Prepared By" so one shared login can
 * still show who actually encoded a document. Encoders never sign in, only a full admin manages them.
 */
class EncoderPreparedByTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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
    }

    protected function encoder(string $name = 'Maria Encoder', bool $listed = true): User
    {
        return User::factory()->create(['name' => $name, 'role' => 'user', 'is_active' => $listed, 'is_encoder' => true]);
    }

    public function test_admin_adds_an_encoder_who_cannot_log_in(): void
    {
        $this->actingAs($this->admin)->post(route('users.encoders.store'), ['name' => 'Maria Santos'])
            ->assertRedirect(route('users.index'));

        $encoder = User::where('name', 'Maria Santos')->firstOrFail();
        $this->assertTrue($encoder->is_encoder);
        $this->assertTrue($encoder->is_active);
        $this->assertStringEndsWith('@encoder.invalid', $encoder->email);
        $this->assertDatabaseHas('activity_logs', ['module' => 'User', 'action' => 'created', 'loggable_id' => $encoder->id]);

        // Even an Encoder session is thrown out on the next request.
        auth()->logout();
        $this->actingAs($encoder)->get(route('users.index'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_only_a_full_admin_can_manage_encoders(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'is_active' => true]);
        $existing = $this->encoder();

        $this->actingAs($staff)->post(route('users.encoders.store'), ['name' => 'Sneaky']);
        $this->actingAs($staff)->post(route('users.encoders.toggle', $existing));

        $this->assertDatabaseMissing('users', ['name' => 'Sneaky']);
        $this->assertTrue($existing->fresh()->is_active);
    }

    public function test_a_duplicate_encoder_name_is_refused(): void
    {
        $this->encoder('Maria Santos');

        $this->actingAs($this->admin)->post(route('users.encoders.store'), ['name' => 'maria santos']);

        $this->assertSame(1, User::where('is_encoder', true)->count());
    }

    public function test_a_removed_encoder_leaves_the_list_but_stays_on_a_document_that_used_them(): void
    {
        $listed = $this->encoder('Listed One');
        $removed = $this->encoder('Removed One', listed: false);

        $names = User::preparers()->pluck('name');
        $this->assertTrue($names->contains('Listed One'));
        $this->assertFalse($names->contains('Removed One'));
        $this->assertTrue($names->contains($this->admin->name), 'login accounts always listed');

        $this->assertTrue(User::preparers($removed->id)->pluck('name')->contains('Removed One'));
        $this->assertTrue(User::preparers([$removed->id])->pluck('name')->contains('Removed One'));

        $this->actingAs($this->admin)->post(route('users.encoders.toggle', $listed));
        $this->assertFalse($listed->fresh()->is_active);
        $this->actingAs($this->admin)->post(route('users.encoders.toggle', $listed));
        $this->assertTrue($listed->fresh()->is_active);
    }

    public function test_an_encoder_is_never_deleted_or_suspended_through_the_user_screens(): void
    {
        $encoder = $this->encoder();

        $this->actingAs($this->admin)->delete(route('users.destroy', $encoder));
        $this->actingAs($this->admin)->post(route('users.toggle-status', $encoder));

        $this->assertNotNull($encoder->fresh());
        $this->assertTrue($encoder->fresh()->is_active);
    }

    public function test_users_page_lists_login_accounts_and_encoders_separately(): void
    {
        $this->encoder('Maria Encoder');

        $this->actingAs($this->admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Maria Encoder')
            ->assertSee('Add Encoder');
    }

    public function test_customer_payment_records_the_chosen_preparer(): void
    {
        $encoder = $this->encoder();
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->post(route('customers.payments.store', $customer), [
            'type' => 'collection', 'amount' => 100, 'payment_date' => now()->toDateString(), 'prepared_by' => $encoder->id,
        ]);

        $this->assertSame($encoder->id, $customer->payments()->firstOrFail()->prepared_by);
    }

    public function test_customer_payment_falls_back_to_the_signed_in_user(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->admin)->post(route('customers.payments.store', $customer), [
            'type' => 'collection', 'amount' => 100, 'payment_date' => now()->toDateString(),
        ]);

        $this->assertSame($this->admin->id, $customer->payments()->firstOrFail()->prepared_by);
    }

    public function test_supplier_payment_records_the_chosen_preparer(): void
    {
        $encoder = $this->encoder();
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->admin)->post(route('suppliers.payments.store', $supplier), [
            'type' => 'payment', 'amount' => 100, 'payment_date' => now()->toDateString(), 'prepared_by' => $encoder->id,
        ]);

        $this->assertSame($encoder->id, $supplier->payments()->firstOrFail()->prepared_by);
    }
}
