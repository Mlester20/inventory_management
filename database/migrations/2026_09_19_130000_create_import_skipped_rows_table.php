<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Standalone log — deliberately no foreign keys, so it can never block
        // or be affected by changes to products/customers/suppliers/users.
        Schema::create('import_skipped_rows', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->string('import_type', 20);
            $table->unsignedInteger('sheet_row')->nullable();
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->json('row_data')->nullable();
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();

            $table->index(['import_type', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_skipped_rows');
    }
};
