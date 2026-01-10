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
        Schema::table('subscription_history', function (Blueprint $table) {
            // Add plan_id to store subscription plan ID for easier querying
            if (!Schema::hasColumn('subscription_history', 'plan_id')) {
                $table->string('plan_id', 255)->nullable()->after('user_id');
            }
            
            // Add vendor_id to link subscription history to vendor
            if (!Schema::hasColumn('subscription_history', 'vendor_id')) {
                $table->string('vendor_id', 255)->nullable()->after('plan_id');
            }
            
            // Add transaction_id to store payment transaction ID
            if (!Schema::hasColumn('subscription_history', 'transaction_id')) {
                $table->string('transaction_id', 255)->nullable()->after('payment_type');
            }
            
            // Add payment_date to store when payment was made
            if (!Schema::hasColumn('subscription_history', 'payment_date')) {
                $table->datetime('payment_date')->nullable()->after('transaction_id');
            }
            
            // Add bill_status to track payment status (paid/unpaid)
            if (!Schema::hasColumn('subscription_history', 'bill_status')) {
                $table->string('bill_status', 50)->nullable()->default('paid')->after('payment_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_history', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_history', 'bill_status')) {
                $table->dropColumn('bill_status');
            }
            if (Schema::hasColumn('subscription_history', 'payment_date')) {
                $table->dropColumn('payment_date');
            }
            if (Schema::hasColumn('subscription_history', 'transaction_id')) {
                $table->dropColumn('transaction_id');
            }
            if (Schema::hasColumn('subscription_history', 'vendor_id')) {
                $table->dropColumn('vendor_id');
            }
            if (Schema::hasColumn('subscription_history', 'plan_id')) {
                $table->dropColumn('plan_id');
            }
        });
    }
};

