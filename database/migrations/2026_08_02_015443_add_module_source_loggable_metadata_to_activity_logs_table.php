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
        Schema::table('activity_logs', function (Blueprint $table) {
            // Which module/feature the action belongs to (e.g. "Customer",
            // "DeliveryReceipt", "Auth") — lets the viewer filter by area
            // without parsing the free-text description.
            $table->string('module')->nullable()->after('user_id');

            // Which system surface the action was performed from — Admin
            // backend vs POS terminal, per the refactor's core requirement.
            $table->string('source')->default('admin')->after('module');

            // Polymorphic reference to the affected record, so a log entry
            // can link straight to (e.g.) the Customer or Delivery Receipt
            // it's about, not just describe it in prose.
            $table->string('loggable_type')->nullable()->after('description');
            $table->unsignedBigInteger('loggable_id')->nullable()->after('loggable_type');

            // Structured context (e.g. before/after values on an update).
            // Never sensitive data — enforced in the logging mechanism, not
            // just by convention (see ActivityLog::record()).
            $table->json('metadata')->nullable()->after('loggable_id');

            $table->index(['loggable_type', 'loggable_id']);
            $table->index('module');
            $table->index('source');
        });

        // An audit log should survive the user account it references being
        // deleted (e.g. an admin removes a departed staff member's account)
        // — cascade-deleting the log rows would destroy the audit trail
        // exactly when it might matter most. Swap cascade -> null-on-delete.
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['loggable_type', 'loggable_id']);
            $table->dropIndex(['module']);
            $table->dropIndex(['source']);
            $table->dropColumn(['module', 'source', 'loggable_type', 'loggable_id', 'metadata']);
        });
    }
};
