<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration ensures a commission plan exists in subscription_plans table.
     * The commission plan will have plan_type = 'commission' and store commission percentage in 'place' column.
     */
    public function up(): void
    {
        // Check if plan_type column exists, if not, we'll use place > 0 to identify commission plans
        $hasPlanTypeColumn = false;
        try {
            $columns = DB::select("SHOW COLUMNS FROM subscription_plans LIKE 'plan_type'");
            $hasPlanTypeColumn = !empty($columns);
        } catch (\Exception $e) {
            $hasPlanTypeColumn = false;
        }

        // Get default commission from AdminCommission setting or use 30%
        $adminCommissionSetting = DB::table('settings')
            ->where('document_name', 'AdminCommission')
            ->first();
        
        $defaultCommission = 30;
        if ($adminCommissionSetting && !empty($adminCommissionSetting->fields)) {
            $fields = json_decode($adminCommissionSetting->fields, true);
            $defaultCommission = (float)($fields['commission'] ?? $fields['fix_commission'] ?? 30);
        }

        // Check if commission plan already exists
        $commissionPlanExists = false;
        
        if ($hasPlanTypeColumn) {
            $existingPlan = DB::table('subscription_plans')
                ->where('plan_type', 'commission')
                ->where('isEnable', 1)
                ->first();
            $commissionPlanExists = $existingPlan !== null;
        } else {
            // Fallback: check for plans with place > 0 (commission plans)
            $existingPlan = DB::table('subscription_plans')
                ->where('place', '>', 0)
                ->where('isEnable', 1)
                ->orderBy('place', 'asc')
                ->first();
            $commissionPlanExists = $existingPlan !== null;
        }

        // If commission plan doesn't exist, create it
        if (!$commissionPlanExists) {
            $commissionPlanId = 'commission_plan_' . time();
            
            $planData = [
                'id' => $commissionPlanId,
                'name' => 'Commission Plan',
                'price' => 0,
                'place' => $defaultCommission, // Store commission percentage in place column
                'expiryDay' => '-1', // Unlimited (commission doesn't expire)
                'description' => 'Commission-based pricing model. Commission percentage is stored in the place field.',
                'type' => 'free',
                'isEnable' => 1,
                'features' => json_encode([]),
                'plan_points' => json_encode([]),
                'orderLimit' => -1,
                'itemLimit' => -1,
                'zone' => json_encode([]), // Available for all zones
            ];

            // Add plan_type if column exists
            if ($hasPlanTypeColumn) {
                $planData['plan_type'] = 'commission';
            }

            DB::table('subscription_plans')->insert($planData);
        } else {
            // Update existing commission plan to ensure it has plan_type set
            if ($hasPlanTypeColumn && $existingPlan) {
                DB::table('subscription_plans')
                    ->where('id', $existingPlan->id)
                    ->update(['plan_type' => 'commission']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete the commission plan as it may be in use
        // This migration is idempotent and safe to run multiple times
    }
};

