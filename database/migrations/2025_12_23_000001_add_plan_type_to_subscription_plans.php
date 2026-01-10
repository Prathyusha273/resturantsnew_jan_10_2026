<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds plan_type column to subscription_plans table if it doesn't exist.
     * Sets plan_type = 'commission' for commission plans and 'subscription' for subscription plans.
     */
    public function up(): void
    {
        // Check if plan_type column already exists
        $hasPlanTypeColumn = false;
        try {
            $columns = DB::select("SHOW COLUMNS FROM subscription_plans LIKE 'plan_type'");
            $hasPlanTypeColumn = !empty($columns);
        } catch (\Exception $e) {
            $hasPlanTypeColumn = false;
        }

        // Add plan_type column if it doesn't exist
        if (!$hasPlanTypeColumn) {
            try {
                DB::statement("ALTER TABLE subscription_plans ADD COLUMN plan_type VARCHAR(20) NULL AFTER type");
            } catch (\Exception $e) {
                // Column might already exist or table structure is different
                \Log::warning('Could not add plan_type column: ' . $e->getMessage());
            }
        }

        // First, identify the commission plan (the one that should be commission-only)
        // Commission plan is typically the one with name like 'Commission Plan' or the one explicitly marked
        $commissionPlan = DB::table('subscription_plans')
            ->where(function($query) {
                $query->where('plan_type', 'commission')
                      ->orWhere('name', 'LIKE', '%Commission Plan%')
                      ->orWhere('name', 'LIKE', '%commission%');
            })
            ->where('isEnable', 1)
            ->first();
        
        // If not found by name, find by price=0 and highest place value (commission plan is free and has commission %)
        if (!$commissionPlan) {
            $commissionPlan = DB::table('subscription_plans')
                ->where('price', 0)
                ->where('isEnable', 1)
                ->orderBy('place', 'desc')
                ->first();
        }
        
        $commissionPlanId = $commissionPlan ? $commissionPlan->id : null;

        // Update existing plans to set plan_type
        // Only the commission plan should be 'commission', all others are 'subscription'
        try {
            if ($commissionPlanId) {
                // Set commission plan
                DB::table('subscription_plans')
                    ->where('id', $commissionPlanId)
                    ->update(['plan_type' => 'commission']);
                
                // Set all other plans to subscription
                DB::table('subscription_plans')
                    ->where('id', '!=', $commissionPlanId)
                    ->where(function($query) {
                        $query->whereNull('plan_type')
                              ->orWhere('plan_type', '');
                    })
                    ->update(['plan_type' => 'subscription']);
            } else {
                // Fallback: if no commission plan found, use place value logic
                // But only for plans with price=0 and place>0 (likely commission plans)
                DB::table('subscription_plans')
                    ->whereNull('plan_type')
                    ->orWhere('plan_type', '')
                    ->update([
                        'plan_type' => DB::raw("CASE 
                            WHEN price = 0 AND place > 0 THEN 'commission'
                            ELSE 'subscription'
                        END")
                    ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Could not update plan_type for existing plans: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop the column as it may be in use
        // This migration is safe to run multiple times
    }
};

