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
        // Global Search currently scans these columns with LIKE '%query%' — a
        // leading wildcard no B-tree index can use. FULLTEXT + MATCH AGAINST
        // (see SearchController) replaces that with an indexed, relevance-
        // ranked lookup. All 4 tables are InnoDB, which supports FULLTEXT
        // natively since MySQL 5.6 — no engine change needed.
        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['item_name', 'description'], 'products_search_fulltext');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->fullText(['customer_name', 'contact_person', 'email', 'contact_number'], 'customers_search_fulltext');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->fullText(['supplier_name', 'contact_person', 'email', 'contact_number'], 'suppliers_search_fulltext');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->fullText(['category_name'], 'categories_search_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText('products_search_fulltext');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropFullText('customers_search_fulltext');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropFullText('suppliers_search_fulltext');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropFullText('categories_search_fulltext');
        });
    }
};
