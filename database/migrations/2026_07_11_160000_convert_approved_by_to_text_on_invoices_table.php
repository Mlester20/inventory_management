<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Preserve any existing approved_by user references as plain names
        // before the column changes from a users FK to free text.
        $approvals = DB::table('invoices')
            ->whereNotNull('approved_by')
            ->pluck('approved_by', 'id');

        $userNames = DB::table('users')->pluck('name', 'id');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn('approved_by');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('approved_by')->nullable()->after('prepared_by');
        });

        foreach ($approvals as $invoiceId => $userId) {
            DB::table('invoices')->where('id', $invoiceId)->update([
                'approved_by' => $userNames[$userId] ?? null,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('approved_by');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('prepared_by')
                ->constrained('users')->nullOnDelete();
        });
    }
};
