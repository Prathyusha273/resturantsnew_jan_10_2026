<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRestaurantRequest;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Models\VendorCuisine;
use App\Models\Zone;
use App\Services\FirebaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Razorpay\Api\Api;
use Carbon\Carbon;

class RestaurantProfileController extends Controller
{
    protected ?array $currencyMeta = null;
    protected FirebaseStorageService $firebaseStorage;

    protected const FILTER_OPTIONS = [
        'Free Wi-Fi' => 'lang.free_wi_fi',
        'Good for Breakfast' => 'lang.good_for_breakfast',
        'Good for Dinner' => 'lang.good_for_dinner',
        'Good for Lunch' => 'lang.good_for_lunch',
        'Live Music' => 'lang.Live_Music',
        'Outdoor Seating' => 'lang.outdoor_seating',
        'Takes Reservations' => 'lang.takes_reservations',
        'Vegetarian Friendly' => 'lang.vegetarian_friendly',
    ];

    public function __construct(FirebaseStorageService $firebaseStorage)
    {
        $this->middleware('auth');
        $this->firebaseStorage = $firebaseStorage;
    }

    public function show(): View
    {
        /** @var User $user */
        $user = Auth::user();

        // Cache settings (5 minutes)
        $settings = \Illuminate\Support\Facades\Cache::remember('settings_admin_placeholder', 300, function () {
            return DB::table('settings')
                ->whereIn('document_name', ['AdminCommission', 'placeholderImage', 'razorpaySettings'])
                ->select(['document_name', 'fields'])
                ->get()
                ->keyBy('document_name')
                ->map(function ($setting) {
                    $setting->fields = json_decode($setting->fields ?? '[]', true) ?? [];
                    return $setting;
                });
        });

        $vendor = $this->findVendorForUser($user);

        // Cache story (5 minutes)
        $story = $vendor
            ? \Illuminate\Support\Facades\Cache::remember('story_vendor_' . $vendor->id, 300, function () use ($vendor) {
                return Story::where('vendor_id', $vendor->id)->latest('created_at')->first();
            })
            : null;

        // Cache zones, cuisines, categories (5 minutes)
        $zones = \Illuminate\Support\Facades\Cache::remember('zones_active', 300, function () {
            return Zone::active()->orderBy('name')->select(['id', 'name'])->get();
        });

        $cuisines = \Illuminate\Support\Facades\Cache::remember('cuisines_active', 300, function () {
            return VendorCuisine::active()->orderBy('title')->select(['id', 'title'])->get();
        });

        $categories = \Illuminate\Support\Facades\Cache::remember('categories_active', 300, function () {
            return VendorCategory::active()->orderBy('title')->select(['id', 'title'])->get();
        });

        // Fetch subscription plan if vendor has one
        $subscriptionPlan = null;
        $hasSubscription = false;
        
        // Clean up subscription fields if subscriptionPlanId is NULL but fields still exist
        if ($vendor && empty($vendor->subscriptionPlanId)) {
            $hasSubscriptionData = !empty($vendor->subscription_plan) || 
                                   !empty($vendor->subscriptionExpiryDate) || 
                                   !empty($vendor->subscriptionPaymentDate) || 
                                   !empty($vendor->subscriptionTransactionId) ||
                                   !empty($vendor->bill_status);
            
            if ($hasSubscriptionData) {
                // Clean up all subscription-related fields
                $vendor->subscription_plan = null;
                $vendor->subscriptionExpiryDate = null;
                $vendor->subscriptionTotalOrders = null;
                $vendor->subscriptionTransactionId = null;
                $vendor->subscriptionPaymentDate = null;
                $vendor->bill_status = null;
                $vendor->save();
                
                \Log::info("Cleaned up subscription fields for vendor", [
                    'vendor_id' => $vendor->id,
                    'reason' => 'subscriptionPlanId is NULL but subscription fields exist'
                ]);
            }
        } elseif ($vendor && !empty($vendor->subscriptionPlanId)) {
            $subscriptionPlan = DB::table('subscription_plans')
                ->where('id', $vendor->subscriptionPlanId)
                ->where('isEnable', 1)
                ->first();
            $hasSubscription = $subscriptionPlan !== null;
            
            // If subscriptionPlanId exists but plan is not found or disabled, clean up
            if (!$hasSubscription) {
                $invalidPlanId = $vendor->subscriptionPlanId; // Store before clearing
                $vendor->subscriptionPlanId = null;
                $vendor->subscription_plan = null;
                $vendor->subscriptionExpiryDate = null;
                $vendor->subscriptionTotalOrders = null;
                $vendor->subscriptionTransactionId = null;
                $vendor->subscriptionPaymentDate = null;
                $vendor->bill_status = null;
                $vendor->save();
                
                \Log::info("Cleaned up subscription fields - plan not found or disabled", [
                    'vendor_id' => $vendor->id,
                    'plan_id' => $invalidPlanId
                ]);
            } else {
                // Ensure subscription_plan JSON includes the plan ID
                $currentPlanData = is_array($vendor->subscription_plan) ? $vendor->subscription_plan : (is_string($vendor->subscription_plan) ? json_decode($vendor->subscription_plan, true) : []);
                $needsUpdate = false;
                
                // Check if subscription_plan JSON is missing or doesn't have ID
                if (empty($currentPlanData) || !isset($currentPlanData['id']) || $currentPlanData['id'] !== $vendor->subscriptionPlanId) {
                    $vendor->subscription_plan = [
                        'id' => $subscriptionPlan->id,
                        'name' => $subscriptionPlan->name ?? ($currentPlanData['name'] ?? ''),
                        'price' => $subscriptionPlan->price ?? ($currentPlanData['price'] ?? 0),
                        'place' => $subscriptionPlan->place ?? ($currentPlanData['place'] ?? 0),
                        'expiryDay' => $subscriptionPlan->expiryDay ?? ($currentPlanData['expiryDay'] ?? '-1'),
                        'image' => $subscriptionPlan->image ?? ($currentPlanData['image'] ?? ''),
                        'description' => $subscriptionPlan->description ?? ($currentPlanData['description'] ?? ''),
                        'type' => $subscriptionPlan->type ?? ($currentPlanData['type'] ?? 'paid'),
                    ];
                    $needsUpdate = true;
                }
                
                // Update expiry date if missing or incorrect
                // Use subscriptionPaymentDate as base to ensure expiry date has same time
                if ($subscriptionPlan->expiryDay && $subscriptionPlan->expiryDay !== '-1') {
                        // Parse vendor date as Asia/Kolkata (it's already stored in Asia/Kolkata), otherwise use current time in Asia/Kolkata
                        $baseDate = $vendor->subscriptionPaymentDate 
                            ? Carbon::createFromFormat('Y-m-d H:i:s', $vendor->subscriptionPaymentDate, 'Asia/Kolkata')
                            : now('Asia/Kolkata');
                        // If createFromFormat fails, fallback to parse and set timezone
                        if (!$baseDate && $vendor->subscriptionPaymentDate) {
                            $baseDate = Carbon::parse($vendor->subscriptionPaymentDate)->setTimezone('Asia/Kolkata');
                        }
                        // Format explicitly to store correct time in Asia/Kolkata
                        $expectedExpiry = $baseDate->copy()->addDays((int)$subscriptionPlan->expiryDay)->format('Y-m-d H:i:s');
                    if (!$vendor->subscriptionExpiryDate || $vendor->subscriptionExpiryDate !== $expectedExpiry) {
                        $vendor->subscriptionExpiryDate = $expectedExpiry;
                        $needsUpdate = true;

                    }
                }
                
                if ($needsUpdate) {
                    $vendor->save();
                }
                
                // Check if subscriptionPlanId exists but there's no recent subscription_history entry
                // This handles cases where subscriptionPlanId was changed directly in the database
                try {
                    $email = $user->email;
                    $vendorUser = DB::table('restaurant_vendor_users')->where('email', $email)->first();
                    $userId = $vendorUser ? $vendorUser->uuid : ($user->firebase_id ?? Auth::id());
                    
                    // Check if there's a recent subscription_history entry for this vendor and plan
                    $hasVendorIdColumn = false;
                    try {
                        $columns = DB::select("SHOW COLUMNS FROM subscription_history LIKE 'vendor_id'");
                        $hasVendorIdColumn = !empty($columns);
                    } catch (\Exception $e) {
                        $hasVendorIdColumn = false;
                    }
                    
                    $recentSubscription = null;
                    if ($hasVendorIdColumn) {
                        $recentSubscription = DB::table('subscription_history')
                            ->where('vendor_id', $vendor->id)
                            ->where('plan_id', $vendor->subscriptionPlanId)
                            ->orderBy('createdAt', 'desc')
                            ->first();
                    } else {
                        $recentSubscription = DB::table('subscription_history')
                            ->where('user_id', $userId)
                            ->whereRaw("JSON_EXTRACT(subscription_plan, '$.id') = ?", [$vendor->subscriptionPlanId])
                            ->orderBy('createdAt', 'desc')
                            ->first();
                    }
                    
                    // If no recent subscription_history entry found, create one
                    if (!$recentSubscription) {
                        // Use vendor's subscriptionPaymentDate as base to ensure expiry date has same time
                        // Parse vendor date as Asia/Kolkata (it's already stored in Asia/Kolkata), otherwise use current time in Asia/Kolkata
                        $baseDate = $vendor->subscriptionPaymentDate 
                            ? Carbon::createFromFormat('Y-m-d H:i:s', $vendor->subscriptionPaymentDate, 'Asia/Kolkata')
                            : now('Asia/Kolkata');
                        // If createFromFormat fails, fallback to parse and set timezone
                        if (!$baseDate && $vendor->subscriptionPaymentDate) {
                            $baseDate = Carbon::parse($vendor->subscriptionPaymentDate)->setTimezone('Asia/Kolkata');
                        }
                        // Format explicitly to store correct time in Asia/Kolkata
                        $currentTimestamp = $baseDate->format('Y-m-d H:i:s');
                        $expiryDay = $subscriptionPlan->expiryDay ?? '-1';
                        $expiryDate = null;
                        if ($expiryDay !== '-1' && is_numeric($expiryDay) && $expiryDay > 0) {
                            // Use same base date to ensure expiry date has same time as payment date
                            // Format explicitly to store correct time in Asia/Kolkata
                            $expiryDate = $baseDate->copy()->addDays((int)$expiryDay)->format('Y-m-d H:i:s');
                        }
                        
                        $fullPlanData = [
                            'id' => $subscriptionPlan->id,
                            'name' => $subscriptionPlan->name ?? '',
                            'price' => $subscriptionPlan->price ?? 0,
                            'place' => $subscriptionPlan->place ?? 0,
                            'expiryDay' => $expiryDay,
                            'image' => $subscriptionPlan->image ?? '',
                            'description' => $subscriptionPlan->description ?? '',
                            'type' => $subscriptionPlan->type ?? 'paid',
                            'features' => json_decode($subscriptionPlan->features ?? '{}', true),
                            'plan_points' => json_decode($subscriptionPlan->plan_points ?? '[]', true),
                            'orderLimit' => $subscriptionPlan->orderLimit ?? -1,
                            'itemLimit' => $subscriptionPlan->itemLimit ?? -1,
                        ];
                        
                        $subscriptionHistoryId = 'sub_' . time() . '_' . uniqid();
                        $historyData = [
                            'id' => $subscriptionHistoryId,
                            'user_id' => $userId,
                            'expiry_date' => $expiryDate,
                            'createdAt' => $currentTimestamp,
                            'subscription_plan' => json_encode($fullPlanData),
                            'payment_type' => ($subscriptionPlan->price ?? 0) > 0 ? 'manual' : 'free',
                        ];
                        
                        // Add new columns if they exist
                        try {
                            $columns = DB::select("SHOW COLUMNS FROM subscription_history");
                            $columnNames = array_column($columns, 'Field');
                            
                            if (in_array('plan_id', $columnNames)) {
                                $historyData['plan_id'] = $vendor->subscriptionPlanId;
                            }
                            if (in_array('vendor_id', $columnNames)) {
                                $historyData['vendor_id'] = $vendor->id;
                            }
                            if (in_array('transaction_id', $columnNames)) {
                                $historyData['transaction_id'] = $vendor->subscriptionTransactionId ?? null;
                            }
                            if (in_array('payment_date', $columnNames)) {
                                $historyData['payment_date'] = $vendor->subscriptionPaymentDate ?? $currentTimestamp;
                            }
                            if (in_array('bill_status', $columnNames)) {
                                $historyData['bill_status'] = $vendor->bill_status ?? 'paid';
                            }
                            // Store zone from vendor
                            if (in_array('zone', $columnNames)) {
                                $historyData['zone'] = $vendor->zoneId ?? $vendor->zone_slug ?? null;
                            }
                            // Set updated_at on insert
                            if (in_array('updated_at', $columnNames)) {
                                $historyData['updated_at'] = $currentTimestamp;
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Could not check subscription_history columns: ' . $e->getMessage());
                        }
                        
                        DB::table('subscription_history')->insert($historyData);
                        
                        \Log::info("Created missing subscription_history entry", [
                            'vendor_id' => $vendor->id,
                            'plan_id' => $vendor->subscriptionPlanId,
                            'subscription_history_id' => $subscriptionHistoryId,
                            'reason' => 'subscriptionPlanId exists but no subscription_history entry found'
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to check/create subscription_history entry: " . $e->getMessage());
                }
            }
        }

        // Get default commission from commission plan using service (fully dynamic, no hard-coded fallback)
        $commissionPlan = \App\Services\SubscriptionPlanService::getCommissionPlan();
        $defaultCommission = 0; // Will be set from commission plan or admin settings
        if ($commissionPlan && isset($commissionPlan->place)) {
            $defaultCommission = is_numeric($commissionPlan->place) ? (float)$commissionPlan->place : 0;
        }
        
        // If commission plan not found or place is 0, try admin commission setting
        if ($defaultCommission <= 0) {
            $adminCommissionSetting = $settings['AdminCommission']->fields ?? [];
            $defaultCommission = (float)($adminCommissionSetting['commission'] ?? $adminCommissionSetting['fix_commission'] ?? 0);
        }
        
        // Final fallback: if still 0, log warning but don't break (admin should configure commission plan)
        if ($defaultCommission <= 0) {
            \Log::warning("No commission percentage found. Please configure commission plan or AdminCommission setting.", [
                'vendor_id' => $vendor->id ?? null
            ]);
        }

        // Fetch all active subscription plans for dropdown
        // Filter by zone_id if vendor has a zone selected
        // Commission plan should always be available (it's zone-agnostic)
        $vendorZoneId = $vendor ? ($vendor->zoneId ?? null) : null;
        $subscriptionPlans = \Illuminate\Support\Facades\Cache::remember('subscription_plans_active_zoneid_' . ($vendorZoneId ?? 'all'), 300, function () use ($vendorZoneId) {
            // Get commission plan first (always available)
            $commissionPlan = \App\Services\SubscriptionPlanService::getCommissionPlan();
            $commissionPlanId = $commissionPlan ? $commissionPlan->id : null;
            
            $query = DB::table('subscription_plans')
                ->where('isEnable', 1);
            
            // Filter by zone_id if vendor has a zone
            // subscription_plans.zone stores JSON array of zone IDs
            // Show plans where the selected zone_id exists in the JSON array OR it's the commission plan
            if ($vendorZoneId) {
                // Get zone name from zone table (cached zones lookup for better performance)
                $zone = \Illuminate\Support\Facades\Cache::remember('zone_' . $vendorZoneId, 3600, function () use ($vendorZoneId) {
                    return DB::table('zone')->where('id', $vendorZoneId)->first();
                });
                
                if ($zone && isset($zone->name)) {
                    // Convert zone name to slug for matching
                    $zoneSlug = \Illuminate\Support\Str::slug($zone->name);
                    
                    // Check if zone_id exists in JSON array OR it's the commission plan
                    $query->where(function($q) use ($vendorZoneId, $zoneSlug, $commissionPlanId) {
                        // Include commission plan always
                        if ($commissionPlanId) {
                            $q->where('id', $commissionPlanId);
                        }
                        
                        // Also include plans matching the zone
                        $q->orWhere(function($zoneQuery) use ($vendorZoneId, $zoneSlug) {
                            // Method 1: Check if zone_id exists in JSON array using JSON_CONTAINS
                            $zoneQuery->whereRaw("(JSON_VALID(zone) = 1 AND JSON_CONTAINS(zone, ?))", [json_encode($vendorZoneId)])
                              // Method 2: Check if zone_slug exists in JSON array (backward compatibility)
                              ->orWhereRaw("(JSON_VALID(zone) = 1 AND JSON_CONTAINS(zone, ?))", [json_encode($zoneSlug)])
                              // Method 3: Legacy - exact string match (for old data: "zone_id_1" or "ongole")
                              ->orWhere('zone', $zoneSlug)
                              ->orWhere('zone', $vendorZoneId);
                        });
                    });
                } else {
                    // If zone not found, only show commission plan
                    if ($commissionPlanId) {
                        $query->where('id', $commissionPlanId);
                    } else {
                        $query->whereRaw('1 = 0'); // Force no results
                    }
                }
            } else {
                // If no zone selected, only show commission plan
                if ($commissionPlanId) {
                    $query->where('id', $commissionPlanId);
                } else {
                    $query->whereRaw('1 = 0'); // Force no results
                }
            }
            
            // Check if plan_type column exists, otherwise use type column
            $columns = ['id', 'name', 'price', 'place', 'expiryDay', 'description', 'type', 'zone'];
            try {
                $planTypeColumns = DB::select("SHOW COLUMNS FROM subscription_plans LIKE 'plan_type'");
                if (!empty($planTypeColumns)) {
                    $columns[] = 'plan_type';
                }
            } catch (\Exception $e) {
                // If check fails, just use type column
            }
            
            $plans = $query->orderBy('place', 'asc')
                ->select($columns)
                ->get();
            
            // Add plan_type property to each plan if it doesn't exist
            // Logic: If place is 0 or null, it's subscription-based (no commission)
            // Otherwise, it's commission-based
            foreach ($plans as $plan) {
                if (!isset($plan->plan_type)) {
                    // If place is 0 or null, it's subscription-based, otherwise commission-based
                    $placeValue = isset($plan->place) ? (float)$plan->place : 0;
                    $plan->plan_type = ($placeValue == 0 || $placeValue == null) ? 'subscription' : 'commission';
                }
            }
            
            // Ensure commission plan is always included in the list (zone-agnostic)
            $commissionPlan = \App\Services\SubscriptionPlanService::getCommissionPlan();
            if ($commissionPlan) {
                $hasCommissionPlan = $plans->contains(function($plan) use ($commissionPlan) {
                    return $plan->id === $commissionPlan->id;
                });
                
                if (!$hasCommissionPlan) {
                    // Add commission plan to the list
                    $commissionPlan->plan_type = $commissionPlan->plan_type ?? 'commission';
                    $plans->push($commissionPlan);
                }
            }
            
            return $plans;
        });

        // Get plan type information for view
        $planInfo = \App\Services\SubscriptionPlanService::getVendorPlanInfo($vendor);
        $planType = $planInfo['planType'];
        $isCommissionPlan = ($planType === 'commission');
        
        // Get actual commission percentage from selected plan (if commission plan) or default
        $actualCommissionPercentage = $defaultCommission;
        if ($hasSubscription && $subscriptionPlan && $isCommissionPlan) {
            // If vendor has commission plan selected, use that plan's commission percentage
            if (isset($subscriptionPlan->place) && is_numeric($subscriptionPlan->place)) {
                $actualCommissionPercentage = (float)$subscriptionPlan->place;
            }
        } elseif ($hasSubscription && $subscriptionPlan && !$isCommissionPlan) {
            // If vendor has subscription plan (not commission), commission is 0
            $actualCommissionPercentage = 0;
        }

        // Get Razorpay settings
        $razorpaySettings = $settings['razorpaySettings']->fields ?? [];
        $razorpayEnabled = $razorpaySettings['isEnabled'] ?? false;
        $razorpayKey = $razorpaySettings['razorpayKey'] ?? '';
        $razorpaySecret = $razorpaySettings['razorpaySecret'] ?? '';

        return view('restaurant.myrestaurant', [
            'user' => $user,
            'vendor' => $vendor,
            'zones' => $zones,
            'cuisines' => $cuisines,
            'categories' => $categories,
            'story' => $story,
            'settings' => $settings,
            'currency' => $this->currencyMeta(),
            'filterOptions' => $this->filterOptions(),
            'subscriptionPlan' => $subscriptionPlan,
            'hasSubscription' => $hasSubscription,
            'planType' => $planType,
            'isCommissionPlan' => $isCommissionPlan,
            'defaultCommission' => $defaultCommission,
            'actualCommissionPercentage' => $actualCommissionPercentage,
            'subscriptionPlans' => $subscriptionPlans,
            'razorpayEnabled' => $razorpayEnabled,
            'razorpayKey' => $razorpayKey,
            'razorpaySecret' => $razorpaySecret,
        ]);
    }

    public function update(UpdateRestaurantRequest $request): RedirectResponse
    {
        $user = Auth::user();

        DB::transaction(function () use ($user, $request) {
            $vendor = $this->ensureVendorExists($user);

            $payload = [
                'title' => $request->string('title')->trim(),
                'restaurant_slug' => $request->input('restaurant_slug'),
                'zone_slug' => $request->input('zone_slug'),
                'zoneId' => $request->input('zone_id'),
                'vType' => $request->input('vendor_type'),
                'phonenumber' => $request->input('phone'),
                'description' => $request->input('description'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'location' => $request->input('location'),
                'restaurantCost' => $request->input('restaurant_cost'),
                'openDineTime' => $request->input('open_dine_time'),
                'closeDineTime' => $request->input('close_dine_time'),
                'isOpen' => $request->boolean('is_open'),
                'enabledDiveInFuture' => $request->boolean('enabled_dine_in_future'),
                'categoryID' => array_values($request->input('category_ids', [])),
                'cuisineID' => $request->input('cuisine_id') ?: null, // Store null if empty instead of empty string
                'gst' => $request->boolean('gst') ? 1 : 0, // Save GST agreement
            ];

            // 👇 Auto-generate createdAt only once
            if (blank($vendor->createdAt)) {
                $payload['createdAt'] = now('Asia/Kolkata')->format('M j, Y g:i A');
            }

            // Category and cuisine titles...
            if ($request->filled('category_ids')) {
                // Use cached categories if available
                $cachedCategories = \Illuminate\Support\Facades\Cache::get('categories_active');
                if ($cachedCategories) {
                    $payload['categoryTitle'] = $cachedCategories
                        ->whereIn('id', $request->category_ids)
                        ->pluck('title')
                        ->filter()
                        ->values()
                        ->all();
                } else {
                    $payload['categoryTitle'] = VendorCategory::whereIn('id', $request->category_ids)
                        ->pluck('title')
                        ->filter()
                        ->values()
                        ->all();
                }
            }

            // Handle cuisine title
            if ($request->filled('cuisine_id')) {
                // Use cached cuisines if available
                $cachedCuisines = \Illuminate\Support\Facades\Cache::get('cuisines_active');
                if ($cachedCuisines) {
                    $payload['cuisineTitle'] = optional($cachedCuisines->firstWhere('id', $request->cuisine_id))->title;
                } else {
                    $payload['cuisineTitle'] = optional(VendorCuisine::find($request->cuisine_id))->title;
                }
            } else {
                // Clear cuisine title if no cuisine selected
                $payload['cuisineTitle'] = null;
            }

            // Admin commission
            if ($request->filled('admin_commission')) {
                $payload['adminCommission'] = [
                    'commissionType' => $request->input('admin_commission_type', 'Percent'),
                    'fix_commission' => $request->input('admin_commission'),
                    'isEnabled' => true,
                ];
            }

            if ($request->filled('vendor_type')) {
                $payload['vType'] = $request->vendor_type === 'restaurant' ? 'Restaurant' : 'Mart';
            }

            // ------------------ Restaurant Main Photo ------------------
            if ($request->boolean('remove_photo')) {
                try {
                    $this->deleteFileIfLocal($vendor->photo ?? null);
                } catch (\Exception $e) {
                    \Log::warning('Failed to delete existing restaurant photo during remove action', [
                        'error' => $e->getMessage(),
                        'vendor_id' => $vendor->id ?? null,
                    ]);
                }
                $payload['photo'] = null;

            } elseif ($request->hasFile('photo') && $request->file('photo')->isValid()) {
                // Delete old image if possible, but never block new image upload.
                try {
                    $this->deleteFileIfLocal($vendor->photo ?? null);
                } catch (\Exception $e) {
                    \Log::warning('Failed to delete existing restaurant photo before upload', [
                        'error' => $e->getMessage(),
                        'vendor_id' => $vendor->id ?? null,
                    ]);
                }

                try {
                    // Primary path: Firebase upload (existing behavior)
                    $payload['photo'] = $this->firebaseStorage->uploadFile(
                        $request->file('photo'),
                        'restaurants/photo_' . time() . '.' . $request->file('photo')->getClientOriginalExtension()
                    );
                } catch (\Exception $e) {
                    // Fallback: save locally so image updates still work when Firebase fails.
                    \Log::error('Failed to upload photo to Firebase Storage', [
                        'error' => $e->getMessage(),
                        'vendor_id' => $vendor->id ?? null
                    ]);
                    try {
                        $localPath = $request->file('photo')->store('restaurants', 'public');
                        $payload['photo'] = Storage::url($localPath);
                    } catch (\Exception $localException) {
                        \Log::error('Failed to upload photo to local storage fallback', [
                            'error' => $localException->getMessage(),
                            'vendor_id' => $vendor->id ?? null,
                        ]);
                        // Final fallback: preserve current value.
                        $payload['photo'] = $vendor->photo ?? null;
                    }
                }
            }

            // ------------------ Gallery ------------------
            $gallery = $vendor->photos ?? [];
            $removeGallery = $request->input('remove_gallery', []);

            // ------------------ Working Hours ------------------
            $workingHoursInput = $request->input('working_hours', []);

            $dbWorkingHours = [];

            foreach ($workingHoursInput as $day => $slots) {
                $cleanSlots = [];

                foreach ($slots as $slot) {
                    if (!empty($slot['from']) && !empty($slot['to'])) {
                        $cleanSlots[] = [
                            'from' => $slot['from'],
                            'to'   => $slot['to'],
                        ];
                    }
                }

                if (!empty($cleanSlots)) {
                    $dbWorkingHours[] = [
                        'day'      => $day,
                        'timeslot' => array_values($cleanSlots),
                    ];
                }
            }

            $payload['workingHours'] = $dbWorkingHours;


            if (!empty($removeGallery)) {
                foreach ($removeGallery as $photo) {
                    $this->deleteFileIfLocal($photo);
                }

                $gallery = array_values(array_filter($gallery, fn ($p) => !in_array($p, $removeGallery)));
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $file) {
                    // Only upload if file is valid
                    if ($file && $file->isValid()) {
                        try {
                    $gallery[] = $this->firebaseStorage->uploadFile(
                        $file,
                        'restaurants/gallery/gallery_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension()
                    );
                        } catch (\Exception $e) {
                            // If Firebase upload fails, log error but continue with other files
                            \Log::error('Failed to upload gallery image to Firebase Storage', [
                                'error' => $e->getMessage(),
                                'file_name' => $file->getClientOriginalName(),
                                'vendor_id' => $vendor->id ?? null
                            ]);
                            // Skip this file, continue with others
                        }
                    }
                }
            }

            $payload['photos'] = $gallery;

            // Filters, working hours, special discounts...
            // Validate subscription plan matches zone (if subscription plan is being set)
            if ($request->filled('subscription_plan_id')) {
                $selectedPlanId = $request->input('subscription_plan_id');
                $zoneId = $request->input('zone_id');
                
                if ($zoneId) {
                    // Get zone name from zone table (cached for performance)
                    $zone = \Illuminate\Support\Facades\Cache::remember('zone_' . $zoneId, 3600, function () use ($zoneId) {
                        return DB::table('zone')->where('id', $zoneId)->first();
                    });
                    
                    if ($zone && isset($zone->name)) {
                        // Convert zone name to slug for matching
                        $zoneSlug = \Illuminate\Support\Str::slug($zone->name);
                        
                        // Check if the selected plan is valid for this zone
                        $selectedPlan = DB::table('subscription_plans')
                            ->where('id', $selectedPlanId)
                            ->where('isEnable', 1)
                            ->first();
                        
                        if ($selectedPlan) {
                            $isValidForZone = false;
                            
                            // Check if zone_id exists in JSON array (zone column stores JSON array of zone IDs)
                            if ($selectedPlan->zone) {
                                $zoneData = is_string($selectedPlan->zone) ? json_decode($selectedPlan->zone, true) : $selectedPlan->zone;
                                
                                if (is_array($zoneData)) {
                                    // Check if zone_id or zone_slug exists in JSON array
                                    $isValidForZone = in_array($zoneId, $zoneData) || in_array($zoneSlug, $zoneData);
                                } else {
                                    // Legacy: Check if zone is a string matching the slug or zone_id
                                    $isValidForZone = ($selectedPlan->zone === $zoneSlug) || ($selectedPlan->zone === $zoneId);
                                }
                            }
                            
                            // NO global plans - plan must explicitly include this zone
                            if (!$isValidForZone) {
                                return redirect()->back()
                                    ->withErrors(['subscription_plan_id' => 'The selected subscription plan is not available for the selected zone.'])
                                    ->withInput();
                            }
                        }
                    }
                }
                
                // Store subscription plan ID in payload (will be handled by subscription payment flow if needed)
                // Note: subscriptionPlanId is typically set through the payment flow, not directly here
            } elseif ($request->has('subscription_plan_id') && $request->input('subscription_plan_id') === '') {
                // Clear subscription plan if empty value is submitted (optional - depends on business logic)
                // $payload['subscriptionPlanId'] = null;
            }
            
            // Check if subscription status changed (before saving)
            $oldSubscriptionPlanId = $vendor->subscriptionPlanId ?? null;
            $oldSubscriptionPlan = $vendor->subscription_plan ?? null;
            
            $vendor->fill($payload);
            $vendor->save();
            
            // Refresh vendor to get updated subscriptionPlanId if it was changed
            $vendor->refresh();

            // Check if subscription status changed (after saving)
            $newSubscriptionPlanId = $vendor->subscriptionPlanId ?? null;
            
            // If subscription status changed, create subscription_history entry and recalculate prices
            if ($oldSubscriptionPlanId !== $newSubscriptionPlanId) {
                try {
                    // If new subscription plan is set, create subscription_history entry
                    if ($newSubscriptionPlanId) {
                        $subscriptionPlan = DB::table('subscription_plans')
                            ->where('id', $newSubscriptionPlanId)
                            ->where('isEnable', 1)
                            ->first();
                        
                        if ($subscriptionPlan) {
                            // Capture current timestamp once to ensure consistency between vendor and subscription_history
                            // Use same timestamp object to ensure payment date and expiry date have the same time
                            // Use Asia/Kolkata timezone to match web page display - format explicitly to store correct time
                            $currentTimestampObj = now('Asia/Kolkata');
                            $currentTimestamp = $currentTimestampObj->format('Y-m-d H:i:s');
                            
                            // Get user ID
                            $email = $user->email;
                            $vendorUser = DB::table('restaurant_vendor_users')->where('email', $email)->first();
                            $userId = $vendorUser ? $vendorUser->uuid : ($user->firebase_id ?? Auth::id());
                            
                            // Get full plan data
                            $fullPlanData = [
                                'id' => $subscriptionPlan->id,
                                'name' => $subscriptionPlan->name ?? '',
                                'price' => $subscriptionPlan->price ?? 0,
                                'place' => $subscriptionPlan->place ?? 0,
                                'expiryDay' => $subscriptionPlan->expiryDay ?? '-1',
                                'image' => $subscriptionPlan->image ?? '',
                                'description' => $subscriptionPlan->description ?? '',
                                'type' => $subscriptionPlan->type ?? 'paid',
                                'features' => json_decode($subscriptionPlan->features ?? '{}', true),
                                'plan_points' => json_decode($subscriptionPlan->plan_points ?? '[]', true),
                                'orderLimit' => $subscriptionPlan->orderLimit ?? -1,
                                'itemLimit' => $subscriptionPlan->itemLimit ?? -1,
                            ];
                            
                            // Calculate expiry date - use same timestamp object to ensure same time as payment date
                            $expiryDay = $subscriptionPlan->expiryDay ?? '-1';
                            $expiryDate = null;
                            if ($expiryDay !== '-1' && is_numeric($expiryDay) && $expiryDay > 0) {
                                // Use copy() to avoid modifying the original timestamp object
                                // Format explicitly to store correct time in Asia/Kolkata
                                $expiryDate = $currentTimestampObj->copy()->addDays((int)$expiryDay)->format('Y-m-d H:i:s');
                            }
                            
                            // Update vendor subscription_plan JSON to include ID
                            $vendor->subscription_plan = [
                                'id' => $subscriptionPlan->id,
                                'name' => $subscriptionPlan->name ?? '',
                                'price' => $subscriptionPlan->price ?? 0,
                                'place' => $subscriptionPlan->place ?? 0,
                                'expiryDay' => $expiryDay,
                                'image' => $subscriptionPlan->image ?? '',
                                'description' => $subscriptionPlan->description ?? '',
                                'type' => $subscriptionPlan->type ?? 'paid',
                            ];
                            $vendor->subscriptionExpiryDate = $expiryDate;
                            $vendor->subscriptionTotalOrders = $expiryDay === '-1' ? '-1' : '0';
                            $vendor->subscriptionPaymentDate = $currentTimestamp; // Use captured timestamp
                            $vendor->bill_status = 'paid';
                            $vendor->save();
                            
                            // Create subscription_history entry
                            $subscriptionHistoryId = 'sub_' . time() . '_' . uniqid();
                            $historyData = [
                                'id' => $subscriptionHistoryId,
                                'user_id' => $userId,
                                'expiry_date' => $expiryDate,
                                'createdAt' => $currentTimestamp, // Use captured timestamp (same as vendor table)
                                'subscription_plan' => json_encode($fullPlanData),
                                'payment_type' => ($subscriptionPlan->price ?? 0) > 0 ? 'manual' : 'free',
                            ];
                            
                            // Add new columns if they exist
                            try {
                                $columns = DB::select("SHOW COLUMNS FROM subscription_history");
                                $columnNames = array_column($columns, 'Field');
                                
                                if (in_array('plan_id', $columnNames)) {
                                    $historyData['plan_id'] = $newSubscriptionPlanId;
                                }
                                if (in_array('vendor_id', $columnNames)) {
                                    $historyData['vendor_id'] = $vendor->id;
                                }
                                if (in_array('transaction_id', $columnNames)) {
                                    $historyData['transaction_id'] = null; // Manual change, no transaction
                                }
                                if (in_array('payment_date', $columnNames)) {
                                    $historyData['payment_date'] = $currentTimestamp; // Use captured timestamp (same as vendor table)
                                }
                                if (in_array('bill_status', $columnNames)) {
                                    $historyData['bill_status'] = 'paid';
                                }
                                // Store zone from vendor
                                if (in_array('zone', $columnNames)) {
                                    $historyData['zone'] = $vendor->zoneId ?? $vendor->zone_slug ?? null;
                                }
                                // Set updated_at on insert
                                if (in_array('updated_at', $columnNames)) {
                                    $historyData['updated_at'] = $currentTimestamp;
                                }
                            } catch (\Exception $e) {
                                \Log::warning('Could not check subscription_history columns: ' . $e->getMessage());
                            }
                            
                            DB::table('subscription_history')->insert($historyData);
                            
                            \Log::info("Created subscription_history entry after plan change", [
                                'vendor_id' => $vendor->id,
                                'old_plan_id' => $oldSubscriptionPlanId,
                                'new_plan_id' => $newSubscriptionPlanId,
                                'subscription_history_id' => $subscriptionHistoryId
                            ]);
                        }
                    } else {
                        // Subscription was removed - clean up vendor table and create cancellation entry in history
                        $vendor->subscription_plan = null;
                        $vendor->subscriptionExpiryDate = null;
                        $vendor->subscriptionTotalOrders = null;
                        $vendor->subscriptionTransactionId = null;
                        $vendor->subscriptionPaymentDate = null;
                        $vendor->bill_status = null;
                        $vendor->save();
                        
                        // Create a cancellation entry in subscription_history if there was a previous subscription
                        if ($oldSubscriptionPlanId) {
                            try {
                                $email = $user->email;
                                $vendorUser = DB::table('restaurant_vendor_users')->where('email', $email)->first();
                                $userId = $vendorUser ? $vendorUser->uuid : ($user->firebase_id ?? Auth::id());
                                
                                // Use Asia/Kolkata timezone to match web page display
                                // Format explicitly to store correct time in Asia/Kolkata
                                $currentTimestamp = now('Asia/Kolkata')->format('Y-m-d H:i:s');
                                $subscriptionHistoryId = 'sub_cancelled_' . time() . '_' . uniqid();
                                
                                // Get old subscription plan data if available
                                $oldPlanData = $oldSubscriptionPlan;
                                if (is_string($oldPlanData)) {
                                    $oldPlanData = json_decode($oldPlanData, true);
                                }
                                
                                $historyData = [
                                    'id' => $subscriptionHistoryId,
                                    'user_id' => $userId,
                                    'expiry_date' => $currentTimestamp, // Set expiry to now (cancelled)
                                    'createdAt' => $currentTimestamp,
                                    'subscription_plan' => is_array($oldPlanData) ? json_encode($oldPlanData) : json_encode(['id' => $oldSubscriptionPlanId, 'name' => 'Cancelled Plan']),
                                    'payment_type' => 'cancelled',
                                ];
                                
                                // Add new columns if they exist
                                try {
                                    $columns = DB::select("SHOW COLUMNS FROM subscription_history");
                                    $columnNames = array_column($columns, 'Field');
                                    
                                    if (in_array('plan_id', $columnNames)) {
                                        $historyData['plan_id'] = $oldSubscriptionPlanId;
                                    }
                                    if (in_array('vendor_id', $columnNames)) {
                                        $historyData['vendor_id'] = $vendor->id;
                                    }
                                    if (in_array('transaction_id', $columnNames)) {
                                        $historyData['transaction_id'] = null;
                                    }
                                    if (in_array('payment_date', $columnNames)) {
                                        $historyData['payment_date'] = $currentTimestamp;
                                    }
                                    if (in_array('bill_status', $columnNames)) {
                                        $historyData['bill_status'] = 'cancelled';
                                    }
                                    // Store zone from vendor
                                    if (in_array('zone', $columnNames)) {
                                        $historyData['zone'] = $vendor->zoneId ?? $vendor->zone_slug ?? null;
                                    }
                                    // Set updated_at on insert
                                    if (in_array('updated_at', $columnNames)) {
                                        $historyData['updated_at'] = $currentTimestamp;
                                    }
                                } catch (\Exception $e) {
                                    \Log::warning('Could not check subscription_history columns: ' . $e->getMessage());
                                }
                                
                                DB::table('subscription_history')->insert($historyData);
                                
                                \Log::info("Created cancellation entry in subscription_history", [
                                    'vendor_id' => $vendor->id,
                                    'old_plan_id' => $oldSubscriptionPlanId,
                                    'subscription_history_id' => $subscriptionHistoryId
                                ]);
                            } catch (\Exception $e) {
                                \Log::warning("Failed to create cancellation entry: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // Recalculate product prices
                    $foodController = app(\App\Http\Controllers\FoodController::class);
                    $updatedCount = $foodController->recalculateProductPrices($vendor);
                    
                    \Log::info("Recalculated product prices after subscription status change", [
                        'vendor_id' => $vendor->id,
                        'old_plan_id' => $oldSubscriptionPlanId,
                        'new_plan_id' => $newSubscriptionPlanId,
                        'products_updated' => $updatedCount
                    ]);
                } catch (\Exception $e) {
                    \Log::warning("Failed to handle subscription status change: " . $e->getMessage());
                    // Don't fail the update if subscription handling fails
                }
            }

            $this->storeStoryMedia($vendor, $request);

            // Clear relevant caches after update
            \Illuminate\Support\Facades\Cache::forget('story_vendor_' . $vendor->id);
            $this->clearVendorCache();
        });

        return redirect()
            ->route('restaurant')
            ->with('success', 'Restaurant details updated successfully.');
    }


    protected function storeImage($file, string $path): string
    {
        return $this->firebaseStorage->uploadFile(
            $file,
            $path . '/' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension()
        );
    }

    protected function findVendorForUser(User $user): ?Vendor
    {
        $vendorId = $user->vendorID ?? $user->getvendorId();

        return $vendorId ? Vendor::find($vendorId) : null;
    }

    protected function ensureVendorExists(User $user): Vendor
    {
        $vendor = $this->findVendorForUser($user);

        if ($vendor) {
            return $vendor;
        }

        $vendorId = $user->vendorID ?? $user->getvendorId() ?? (string) Str::uuid();

        $vendor = Vendor::create([
            'id' => $vendorId,
            'author' => $user->firebase_id ?? $user->_id ?? (string) $user->id,
            'title' => $user->name ?? '',
            'phonenumber' => $user->phoneNumber ?? null,
            'createdAt' => now('Asia/Kolkata')->format('M j, Y g:i A'),
        ]);

        if (empty($user->vendorID)) {
            $user->vendorID = $vendorId;
            $user->save();
        }

        return $vendor;
    }

    protected function prepareWorkingHours(array $input): array
    {
        return collect($input)
            ->map(function ($slots, $day) {
                $timeslots = collect($slots)
                    ->filter(function ($slot) {
                        return !empty($slot['from']) && !empty($slot['to']) && $slot['from'] < $slot['to'];
                    })
                    ->map(function ($slot) {
                        return [
                            'from' => $slot['from'],
                            'to' => $slot['to'],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'day' => $day,
                    'timeslot' => $timeslots,
                ];
            })
            ->filter(fn ($day) => !empty($day['timeslot']))
            ->values()
            ->all();
    }

    protected function prepareSpecialDiscount(array $input): array
    {
        return collect($input)
            ->map(function ($slots, $day) {
                $timeslots = collect($slots)
                    ->map(function ($slot) {
                        $from = $slot['from'] ?? null;
                        $to = $slot['to'] ?? null;
                        $discount = $slot['discount'] ?? null;
                        $type = $slot['type'] ?? 'percentage';
                        $discountType = $slot['discount_type'] ?? 'delivery';

                        if (empty($from) || empty($to) || empty($discount)) {
                            return null;
                        }

                        return [
                            'from' => $from,
                            'to' => $to,
                            'discount' => (float) $discount,
                            'type' => $type,
                            'discount_type' => $discountType,
                        ];
                    })
                    ->filter(fn ($slot) => $slot !== null && $slot['from'] < $slot['to'])
                    ->values()
                    ->all();

                return [
                    'day' => $day,
                    'timeslot' => $timeslots,
                ];
            })
            ->filter(fn ($day) => !empty($day['timeslot']))
            ->values()
            ->all();
    }

    protected function currencyMeta(): array
    {
        if ($this->currencyMeta !== null) {
            return $this->currencyMeta;
        }

        return $this->currencyMeta = $this->getCachedCurrency();
    }

    protected function filterOptions(): array
    {
        return self::FILTER_OPTIONS;
    }

    protected function deleteFileIfLocal(?string $url): void
    {
        if (empty($url)) {
            return;
        }

        // Check if it's a Firebase Storage URL
        if (strpos($url, 'firebasestorage.googleapis.com') !== false) {
            $this->firebaseStorage->deleteFile($url);
            return;
        }

        // Fallback to local storage deletion for backward compatibility
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return;
        }

        $relative = ltrim(str_replace('/storage/', '', $path), '/');
        if (empty($relative)) {
            return;
        }

        if (Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }

    protected function storeVideo($file, string $path): string
    {
        return $this->firebaseStorage->uploadFile(
            $file,
            $path . '/' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension()
        );
    }

    protected function storeStoryMedia(Vendor $vendor, UpdateRestaurantRequest $request): void
    {
        $story = Story::firstOrNew(['vendor_id' => $vendor->id]);
        $hasChanges = false;

        if ($request->hasFile('story_thumbnail')) {
            $this->deleteFileIfLocal($story->video_thumbnail ?? null);
            $story->video_thumbnail = $this->storeImage($request->file('story_thumbnail'), 'stories/thumbnails');
            $hasChanges = true;
        }

        if ($request->hasFile('story_video')) {
            $existingVideo = is_array($story->video_url ?? null) ? ($story->video_url[0] ?? null) : null;
            $this->deleteFileIfLocal($existingVideo);
            $story->video_url = [$this->storeVideo($request->file('story_video'), 'stories/videos')];
            $hasChanges = true;
        }

        if ($hasChanges) {
            $story->vendor_id = $vendor->id;
            $story->save();
        }
    }

    /**
     * Create Razorpay order for subscription payment
     */
    public function createRazorpayOrder(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $vendor = $this->findVendorForUser($user);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found. Please create a restaurant profile first.'
            ], 404);
        }

        $planId = $request->input('plan_id');
        $amount = $request->input('amount');

        if ($amount <= 0) {
            return response()->json([
                'success' => true,
                'order_id' => null,
                'message' => 'Free plan - no payment required'
            ]);
        }

        // Get Razorpay settings
        $razorpaySettings = Setting::getFields('razorpaySettings');
        $razorpayKey = $razorpaySettings['razorpayKey'] ?? '';
        $razorpaySecret = $razorpaySettings['razorpaySecret'] ?? '';

        if (empty($razorpayKey) || empty($razorpaySecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Razorpay is not configured. Please contact administrator.'
            ], 500);
        }

        try {
            $api = new Api($razorpayKey, $razorpaySecret);
            
            // Create a short receipt (max 40 chars for Razorpay)
            // Format: sub_[short_plan_id]_[timestamp]
            // Use first 8 chars of plan_id and last 6 digits of timestamp to keep it under 40
            $shortPlanId = substr(str_replace('-', '', $planId), 0, 8);
            $shortTimestamp = substr(time(), -6); // Last 6 digits of timestamp
            $receipt = 'sub_' . $shortPlanId . '_' . $shortTimestamp;
            
            // Ensure receipt is max 40 characters
            if (strlen($receipt) > 40) {
                $receipt = substr($receipt, 0, 40);
            }
            
            // Create order
            $orderData = [
                'receipt' => $receipt,
                'amount' => (int)($amount * 100), // Amount in paise
                'currency' => 'INR',
                'notes' => [
                    'plan_id' => $planId,
                    'vendor_id' => $vendor->id,
                    'user_id' => $user->id,
                ]
            ];

            $razorpayOrder = $api->order->create($orderData);

            return response()->json([
                'success' => true,
                'order_id' => $razorpayOrder['id'],
                'amount' => $amount,
                'currency' => 'INR',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process subscription payment via Razorpay
     */
    public function processSubscriptionPayment(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|string',
            'plan_name' => 'required|string',
            'plan_price' => 'required|numeric',
            'plan_place' => 'required|numeric',
            'expiry_day' => 'required|string',
        ]);

        $user = Auth::user();
        $vendor = $this->findVendorForUser($user);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found. Please create a restaurant profile first.'
            ], 404);
        }

        $planId = $request->input('plan_id');
        $planName = $request->input('plan_name');
        $planPrice = $request->input('plan_price');
        $planPlace = $request->input('plan_place');
        $expiryDay = $request->input('expiry_day');
        $isFree = $request->input('is_free', false);
        $paymentId = null;

        // Verify payment if not free
        if (!$isFree && $planPrice > 0) {
            $paymentId = $request->input('payment_id');
            $orderId = $request->input('order_id');
            $signature = $request->input('signature');

            if (!$paymentId || !$orderId || !$signature) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment details are missing.'
                ], 400);
            }

            // Get Razorpay settings
            $razorpaySettings = Setting::getFields('razorpaySettings');
            $razorpayKey = $razorpaySettings['razorpayKey'] ?? '';
            $razorpaySecret = $razorpaySettings['razorpaySecret'] ?? '';

            if (empty($razorpayKey) || empty($razorpaySecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay is not configured. Please contact administrator.'
                ], 500);
            }

            // Verify payment signature
            try {
                $api = new Api($razorpayKey, $razorpaySecret);
                $attributes = [
                    'razorpay_order_id' => $orderId,
                    'razorpay_payment_id' => $paymentId,
                    'razorpay_signature' => $signature
                ];
                $api->utility->verifyPaymentSignature($attributes);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed: ' . $e->getMessage()
                ], 400);
            }

            // Verify payment status
            try {
                $payment = $api->payment->fetch($paymentId);
                if ($payment['status'] !== 'captured' && $payment['status'] !== 'authorized') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment is not successful. Status: ' . $payment['status']
                    ], 400);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify payment: ' . $e->getMessage()
                ], 400);
            }
        }

        // Get subscription plan details
        $subscriptionPlan = DB::table('subscription_plans')
            ->where('id', $planId)
            ->where('isEnable', 1)
            ->first();

        if (!$subscriptionPlan) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription plan not found or is not active.'
            ], 404);
        }

        // Calculate expiry date and capture current timestamp (use same timestamp for both tables)
        // Capture timestamp once to ensure payment date and expiry date have the same time
        // Use Asia/Kolkata timezone to match web page display - format explicitly to store correct time
        $currentTimestampObj = now('Asia/Kolkata');
        $currentTimestamp = $currentTimestampObj->format('Y-m-d H:i:s');
        $expiryDate = null;
        if ($expiryDay !== '-1' && is_numeric($expiryDay) && $expiryDay > 0) {
            // Use the same timestamp object to ensure expiry date has same time as payment date
            // Format explicitly to store correct time in Asia/Kolkata
            $expiryDate = $currentTimestampObj->copy()->addDays((int)$expiryDay)->format('Y-m-d H:i:s');
        }

        // Update vendor with subscription details
        try {
            $vendor->subscriptionPlanId = $planId;
            // Include plan ID in subscription_plan JSON for consistency
            $vendor->subscription_plan = [
                'id' => $planId, // Include plan ID
                'name' => $planName,
                'price' => $planPrice,
                'place' => $planPlace,
                'expiryDay' => $expiryDay,
                'image' => $subscriptionPlan->image ?? '',
                'description' => $subscriptionPlan->description ?? '',
                'type' => $subscriptionPlan->type ?? 'paid',
            ];
            $vendor->subscriptionExpiryDate = $expiryDate;
            $vendor->subscriptionTotalOrders = $expiryDay === '-1' ? '-1' : '0';
            $vendor->bill_status = 'paid';
            
            // Store transaction ID and payment date - use same timestamp for consistency
            if (!$isFree && $planPrice > 0 && isset($paymentId)) {
                $vendor->subscriptionTransactionId = $paymentId;
                $vendor->subscriptionPaymentDate = $currentTimestamp; // Use captured timestamp
            } else {
                // For free plans, still set payment date to track when subscription was taken
                $vendor->subscriptionPaymentDate = $currentTimestamp; // Use captured timestamp
            }
            
            $vendor->save();

            // Also save to subscription_history table
            $user = Auth::user();
            $email = $user->email;
            
            // Get userId from restaurant_vendor_users table
            $vendorUser = DB::table('restaurant_vendor_users')->where('email', $email)->first();
            $userId = $vendorUser ? $vendorUser->uuid : ($user->firebase_id ?? Auth::id());
            
            // Get full plan data
            $fullPlanData = [
                'id' => $subscriptionPlan->id,
                'name' => $planName,
                'price' => $planPrice,
                'place' => $planPlace,
                'expiryDay' => $expiryDay,
                'image' => $subscriptionPlan->image ?? '',
                'description' => $subscriptionPlan->description ?? '',
                'type' => $subscriptionPlan->type ?? 'paid',
                'features' => json_decode($subscriptionPlan->features ?? '{}', true),
                'plan_points' => json_decode($subscriptionPlan->plan_points ?? '[]', true),
                'orderLimit' => $subscriptionPlan->orderLimit ?? -1,
                'itemLimit' => $subscriptionPlan->itemLimit ?? -1,
            ];
            
            $subscriptionHistoryId = 'sub_' . time() . '_' . uniqid();
            
            // Build insert data - check if new columns exist (migration might not be run yet)
            // Use same timestamp (currentTimestamp) for createdAt and payment_date to ensure consistency
            $historyData = [
                'id' => $subscriptionHistoryId,
                'user_id' => $userId,
                'expiry_date' => $expiryDate,
                'createdAt' => $currentTimestamp, // Use captured timestamp (same as vendor table)
                'subscription_plan' => json_encode($fullPlanData),
                'payment_type' => $isFree ? 'free' : 'razorpay',
            ];
            
            // Add new columns only if they exist (safe for backward compatibility)
            try {
                $columns = DB::select("SHOW COLUMNS FROM subscription_history");
                $columnNames = array_column($columns, 'Field');
                
                // Store plan_id separately for easier querying
                if (in_array('plan_id', $columnNames)) {
                    $historyData['plan_id'] = $planId;
                }
                
                if (in_array('vendor_id', $columnNames)) {
                    $historyData['vendor_id'] = $vendor->id;
                }
                if (in_array('transaction_id', $columnNames)) {
                    $historyData['transaction_id'] = (!$isFree && $planPrice > 0 && isset($paymentId)) ? $paymentId : null;
                }
                if (in_array('payment_date', $columnNames)) {
                    // Use same timestamp for payment_date (for both free and paid plans)
                    $historyData['payment_date'] = $currentTimestamp; // Use captured timestamp (same as vendor table)
                }
                if (in_array('bill_status', $columnNames)) {
                    $historyData['bill_status'] = 'paid';
                }
                // Store zone from vendor
                if (in_array('zone', $columnNames)) {
                    $historyData['zone'] = $vendor->zoneId ?? $vendor->zone_slug ?? null;
                }
                // Set updated_at on insert
                if (in_array('updated_at', $columnNames)) {
                    $historyData['updated_at'] = $currentTimestamp;
                }
            } catch (\Exception $e) {
                // If column check fails, just insert without new columns (backward compatible)
                \Log::warning('Could not check subscription_history columns: ' . $e->getMessage());
            }
            
            DB::table('subscription_history')->insert($historyData);

            // Recalculate all product prices based on new subscription status
            try {
                $foodController = app(\App\Http\Controllers\FoodController::class);
                $updatedCount = $foodController->recalculateProductPrices($vendor);
                
                \Log::info("Recalculated product prices after subscription change", [
                    'vendor_id' => $vendor->id,
                    'plan_id' => $planId,
                    'products_updated' => $updatedCount
                ]);
            } catch (\Exception $e) {
                \Log::warning("Failed to recalculate product prices after subscription change: " . $e->getMessage());
                // Don't fail the subscription update if price recalculation fails
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully!',
                'subscription' => [
                    'plan_id' => $planId,
                    'plan_name' => $planName,
                    'expiry_date' => $expiryDate,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription plans filtered by zone_id (AJAX endpoint)
     */
    public function getSubscriptionPlansByZone(\Illuminate\Http\Request $request): JsonResponse
    {
        $zoneId = $request->input('zone_id');
        
        // Clear cache for this zone to get fresh data
        \Illuminate\Support\Facades\Cache::forget('subscription_plans_active_zoneid_' . ($zoneId ?? 'all'));
        
        // Fetch subscription plans filtered by zone_id
        // subscription_plans.zone stores JSON array of zone IDs
        // Commission plan should always be available (it's zone-agnostic)
        $subscriptionPlans = \Illuminate\Support\Facades\Cache::remember('subscription_plans_active_zoneid_' . ($zoneId ?? 'all'), 300, function () use ($zoneId) {
            // Get commission plan first (always available)
            $commissionPlan = \App\Services\SubscriptionPlanService::getCommissionPlan();
            $commissionPlanId = $commissionPlan ? $commissionPlan->id : null;
            
            $query = DB::table('subscription_plans')
                ->where('isEnable', 1);
            
            // Filter by zone_id if zone is selected
            // Show plans where the selected zone_id exists in the JSON array OR it's the commission plan
            if ($zoneId) {
                // Get zone name from zone table (cached zones lookup for better performance)
                $zone = \Illuminate\Support\Facades\Cache::remember('zone_' . $zoneId, 3600, function () use ($zoneId) {
                    return DB::table('zone')->where('id', $zoneId)->first();
                });
                
                if ($zone && isset($zone->name)) {
                    // Convert zone name to slug for matching
                    $zoneSlug = \Illuminate\Support\Str::slug($zone->name);
                    
                    // Check if zone_id exists in JSON array OR it's the commission plan
                    $query->where(function($q) use ($zoneId, $zoneSlug, $commissionPlanId) {
                        // Include commission plan always
                        if ($commissionPlanId) {
                            $q->where('id', $commissionPlanId);
                        }
                        
                        // Also include plans matching the zone
                        $q->orWhere(function($zoneQuery) use ($zoneId, $zoneSlug) {
                            // Method 1: Check if zone_id exists in JSON array using JSON_CONTAINS
                            $zoneQuery->whereRaw("(JSON_VALID(zone) = 1 AND JSON_CONTAINS(zone, ?))", [json_encode($zoneId)])
                              // Method 2: Check if zone_slug exists in JSON array (backward compatibility)
                              ->orWhereRaw("(JSON_VALID(zone) = 1 AND JSON_CONTAINS(zone, ?))", [json_encode($zoneSlug)])
                              // Method 3: Legacy - exact string match (for old data: "zone_id_1" or "ongole")
                              ->orWhere('zone', $zoneSlug)
                              ->orWhere('zone', $zoneId);
                        });
                    });
                } else {
                    // If zone not found, only show commission plan
                    if ($commissionPlanId) {
                        $query->where('id', $commissionPlanId);
                    } else {
                        $query->whereRaw('1 = 0'); // Force no results
                    }
                }
            } else {
                // If no zone selected, only show commission plan
                if ($commissionPlanId) {
                    $query->where('id', $commissionPlanId);
                } else {
                    $query->whereRaw('1 = 0'); // Force no results
                }
            }
            
            // Check if plan_type column exists, otherwise use type column
            $columns = ['id', 'name', 'price', 'place', 'expiryDay', 'description', 'type', 'zone'];
            try {
                $planTypeColumns = DB::select("SHOW COLUMNS FROM subscription_plans LIKE 'plan_type'");
                if (!empty($planTypeColumns)) {
                    $columns[] = 'plan_type';
                }
            } catch (\Exception $e) {
                // If check fails, just use type column
            }
            
            $plans = $query->orderBy('place', 'asc')
                ->select($columns)
                ->get();
            
            // Add plan_type property to each plan if it doesn't exist
            foreach ($plans as $plan) {
                if (!isset($plan->plan_type)) {
                    $placeValue = isset($plan->place) ? (float)$plan->place : 0;
                    $plan->plan_type = ($placeValue == 0 || $placeValue == null) ? 'subscription' : 'commission';
                }
            }
            
            // Ensure commission plan is always included in the list (zone-agnostic)
            $commissionPlan = \App\Services\SubscriptionPlanService::getCommissionPlan();
            if ($commissionPlan) {
                $hasCommissionPlan = $plans->contains(function($plan) use ($commissionPlan) {
                    return $plan->id === $commissionPlan->id;
                });
                
                if (!$hasCommissionPlan) {
                    // Add commission plan to the list
                    $commissionPlan->plan_type = $commissionPlan->plan_type ?? 'commission';
                    $plans->push($commissionPlan);
                }
            }
            
            return $plans;
        });

        return response()->json([
            'success' => true,
            'plans' => $subscriptionPlans
        ]);
    }
}

