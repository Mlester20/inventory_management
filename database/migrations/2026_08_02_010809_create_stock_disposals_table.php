<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_disposals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date');
            // Kept as a field (not hardcoded) so a future reason like
            // "Refund to Supplier" can be added later without restructuring
            // this table — only 'Expired' is actually triggered for now.
            $table->string('reason')->default('Expired');
            $table->string('remarks')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_disposals');
    }
};
