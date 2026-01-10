<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Vendor;

class SubscriptionPlanService
{
    /**
     * Get the commission plan from subscription_plans table
     * There should be only ONE commission plan with plan_type = 'commission'
     * 
     * @return object|null Commission plan object or null if not found
     */
    public static function getCommissionPlan()
    {
        return Cache::remember('commission_plan', 3600, function () {
            // First try to find by plan_type column
            $plan = DB::table('subscription_plans')
                ->where('isEnable', 1)
                ->where(function($query) {
                    // Check if plan_type column exists
                    try {
                        $columns = DB::select("SHOW COLUMNS FROM subscription_plans LIKE 'plan_type'");
                        if (!empty($columns)) {
                            $query->where('plan_type', 'commission');
                        } else {
                            // Fallback: use place > 0 to identify commission plans
                            $query->where('place', '>', 0);
                        }
                    } catch (\Exception $e) {
                        // If column check fails, use place > 0
                        $query->where('place', '>', 0);
                    }
                })
                ->orderBy('place', 'asc')
                ->first();
            
            return $plan;
        });
    }

    /**
     * Get commission percentage for a vendor
     * If vendor has a commission plan selected, use that plan's place value
     * Otherwise, use the default commission plan's place value
     * 
     * @param Vendor|null $vendor Vendor object or null
     * @return float Commission percentage (0 if not found - admin should configure commission plan)
     */
    public static function getCommissionPercentage(?Vendor $vendor = null): float
    {
        // If vendor has a subscription plan, check if it's commission-based
        if ($vendor && !empty($vendor->subscriptionPlanId)) {
            $subscriptionPlan = DB::table('subscription_plans')
                ->where('id', $vendor->subscriptionPlanId)
                ->where('isEnable', 1)
                ->first();
            
            if ($subscriptionPlan) {
                // Check plan type
                $planType = $subscriptionPlan->plan_type ?? null;
                
                // If plan_type is not set, determine from place value
                if (!$planType) {
                    $placeValue = (float)($subscriptionPlan->place ?? 0);
                    $planType = ($placeValue == 0 || $placeValue == null) ? 'subscription' : 'commission';
                }
                
                // If it's a commission plan, use its place value
                if ($planType === 'commission' && isset($subscriptionPlan->place)) {
                    $commission = is_numeric($subscriptionPlan->place) ? (float)$subscriptionPlan->place : 0;
                    if ($commission > 0) {
                        return $commission;
                    }
                }
            }
        }
        
        // No commission plan selected or vendor has subscription plan
        // Get default commission plan
        $commissionPlan = self::getCommissionPlan();
        
        if ($commissionPlan && isset($commissionPlan->place)) {
            $commission = is_numeric($commissionPlan->place) ? (float)$commissionPlan->place : 0;
            if ($commission > 0) {
                return $commission;
            }
        }
        
        // Fallback: try to get from AdminCommission setting
        try {
            $adminCommissionSetting = DB::table('settings')
                ->where('document_name', 'AdminCommission')
                ->first();
            
            if ($adminCommissionSetting && !empty($adminCommissionSetting->fields)) {
                $fields = json_decode($adminCommissionSetting->fields, true);
                $commission = (float)($fields['commission'] ?? $fields['fix_commission'] ?? 0);
                if ($commission > 0) {
                    return $commission;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Could not fetch commission from AdminCommission setting: ' . $e->getMessage());
        }
        
        // Return 0 if no commission found (admin should configure commission plan)
        \Log::warning('No commission percentage found. Please configure commission plan or AdminCommission setting.', [
            'vendor_id' => $vendor->id ?? null
        ]);
        return 0.0;
    }

    /**
     * Get subscription plan details for a vendor
     * 
     * @param Vendor|null $vendor Vendor object or null
     * @return array ['plan' => object|null, 'hasSubscription' => bool, 'planType' => string, 'commissionPercentage' => float]
     */
    public static function getVendorPlanInfo(?Vendor $vendor = null): array
    {
        $subscriptionPlan = null;
        $hasSubscription = false;
        $planType = 'commission';
        $commissionPercentage = self::getCommissionPercentage($vendor);
        
        if ($vendor && !empty($vendor->subscriptionPlanId)) {
            $subscriptionPlan = DB::table('subscription_plans')
                ->where('id', $vendor->subscriptionPlanId)
                ->where('isEnable', 1)
                ->first();
            
            $hasSubscription = $subscriptionPlan !== null;
            
            if ($hasSubscription) {
                // Check plan type
                $planType = $subscriptionPlan->plan_type ?? null;
                
                // If plan_type is not set, determine from place value
                if (!$planType) {
                    $placeValue = (float)($subscriptionPlan->place ?? 0);
                    $planType = ($placeValue == 0 || $placeValue == null) ? 'subscription' : 'commission';
                }
                
                // If it's a commission plan, get commission from the plan
                if ($planType === 'commission' && isset($subscriptionPlan->place)) {
                    $commissionPercentage = is_numeric($subscriptionPlan->place) ? (float)$subscriptionPlan->place : $commissionPercentage;
                }
            }
        }
        
        return [
            'plan' => $subscriptionPlan,
            'hasSubscription' => $hasSubscription,
            'planType' => $planType,
            'commissionPercentage' => $commissionPercentage,
        ];
    }

    /**
     * Clear commission plan cache (call after updating commission plan)
     */
    public static function clearCommissionPlanCache(): void
    {
        Cache::forget('commission_plan');
    }
}

