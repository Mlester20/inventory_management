<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per Sir: the destination's Price/Cost is the source's Price/Cost divided
     * evenly by how many destination units one repack produces (e.g. a ₱100
     * box broken into 100 tabs becomes ₱1/tab). These two columns record what
     * was suggested/applied on this line at the time of the Repack — an audit
     * trail independent of the product's own Price/Cost, which can change
     * afterwards. `price_applied` is false when the encoder unchecked
     * "update this item's Price/Cost" and posted with the product's price
     * left as-is.
     */
    public function up(): void
    {
        Schema::table('repack_lines', function (Blueprint $table) {
            $table->decimal('destination_price', 10, 2)->nullable()->after('destination_qty');
            $table->decimal('destination_cost', 10, 2)->nullable()->after('destination_price');
            $table->boolean('price_applied')->default(false)->after('destination_cost');
        });
    }

    public function down(): void
    {
        Schema::table('repack_lines', function (Blueprint $table) {
            $table->dropColumn(['destination_price', 'destination_cost', 'price_applied']);
        });
    }
};
