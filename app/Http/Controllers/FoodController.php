<?php


namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Models\VendorProduct;
use App\Models\MasterProduct;
use App\Services\FirebaseStorageService;
use App\Services\PricingCalculationService;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;

class FoodController extends Controller
{
    protected FirebaseStorageService $firebaseStorage;

    public function __construct(FirebaseStorageService $firebaseStorage)
    {
        $this->middleware('auth');
        $this->firebaseStorage = $firebaseStorage;
    }

    public function index(Request $request)
    {
        $vendor = $this->currentVendor();
        // Clear vendor cache and refresh to get latest GST value
        if ($vendor) {
            $this->clearVendorCache();
            $vendor = Vendor::select(['id', 'title', 'author', 'subscriptionPlanId', 'gst'])->where('id', $vendor->id)->first();
        }

        // Select only needed columns to reduce memory usage
        $query = VendorProduct::with(['category:id,title'])
            ->select([
                'id', 'name', 'description', 'merchant_price', 'price', 'disPrice', 'photo',
                'publish', 'isAvailable', 'categoryID', 'updatedAt', 'available_days', 'available_timings','options','addOnsTitle',
                'addOnsPrice'
            ])
            ->where('vendorID', $vendor->id);

        if ($search = trim((string)$request->get('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('price', 'like', "%{$search}%")
                    ->orWhere('disPrice', 'like', "%{$search}%");
            });
        }

        if ($categoryFilter = $request->get('category')) {
            $query->where('categoryID', $categoryFilter);
        }

        $foods = $query->orderByDesc('updatedAt')->get();

        // Fetch subscription plan info using service
        $planInfo = SubscriptionPlanService::getVendorPlanInfo($vendor);
        $subscriptionPlan = $planInfo['plan'];
        $hasSubscription = $planInfo['hasSubscription'];
        $applyPercentage = $planInfo['commissionPercentage'];
        $planType = $planInfo['planType'];
        $gstAgreed = false;

        if ($vendor) {
            // Get GST agreement
            $gstAgreed = (bool)($vendor->gst ?? 0);
        }

        // Format updatedAt dates (don't recalculate online prices - use stored values)
        $foods->transform(function ($food) {
            $updatedAt = $food->updatedAt;
            if (!$updatedAt) {
                $food->formattedUpdatedAt = '—';
            } else {
                $carbon = is_numeric($updatedAt)
                    ? Carbon::createFromTimestamp((int) $updatedAt)
                    : Carbon::parse($updatedAt);
                $food->formattedUpdatedAt = $carbon->timezone('Asia/Kolkata')->format('M d, Y H:i');
            }

            // Don't recalculate - always use stored price value
            // Manual edits should be preserved

            return $food;
        });

        // Cache categories for 5 minutes
        $categories = Cache::remember('vendor_categories_list', 300, function () {
            return VendorCategory::orderBy('title')->get(['id', 'title']);
        });

        return view('foods.index', [
            'foods' => $foods,
            'categories' => $categories,
            'vendor' => $vendor,
            'placeholderImage' => $this->placeholderImage(),
            'hasSubscription' => $hasSubscription,
            'applyPercentage' => $applyPercentage,
            'planType' => $planType,
            'gstAgreed' => $gstAgreed,
        ]);
    }

    public function create(Request $request)
    {
        $vendor = $this->currentVendor();
        // Clear vendor cache and refresh to get latest GST value
        if ($vendor) {
            $this->clearVendorCache();
            $vendor = Vendor::select(['id', 'title', 'author', 'subscriptionPlanId', 'gst'])->where('id', $vendor->id)->first();
        }

        // Cache categories for 5 minutes - optimized query (select only needed columns)
        $categories = Cache::remember('vendor_categories_list', 300, function () {
            return VendorCategory::select(['id', 'title', 'photo'])
                ->where('publish', true)
                ->orderBy('title')
                ->get();
        });

        // Cache placeholder image for 5 minutes
        $placeholderImage = $this->placeholderImage();

        // Fetch subscription plan info using service
        $planInfo = SubscriptionPlanService::getVendorPlanInfo($vendor);
        $subscriptionPlan = $planInfo['plan'];
        $hasSubscription = $planInfo['hasSubscription'];
        $applyPercentage = $planInfo['commissionPercentage'];
        $planType = $planInfo['planType'];
        $gstAgreed = false;

        if ($vendor) {
            // Get GST agreement
            $gstAgreed = (bool)($vendor->gst ?? 0);
        }

        // If category is selected, fetch master products for that category
        $selectedCategoryId = $request->get('category');
        $masterProducts = collect([]);

        if ($selectedCategoryId) {
            $masterProducts = MasterProduct::where('categoryID', $selectedCategoryId)
                ->where('publish', true)
                ->orderBy('name')
                ->get();
        }

        return view('foods.create', [
            'categories' => $categories,
            'placeholderImage' => $placeholderImage,
            'selectedCategoryId' => $selectedCategoryId,
            'masterProducts' => $masterProducts,
            'vendor' => $vendor,
            'hasSubscription' => $hasSubscription,
            'applyPercentage' => $applyPercentage,
            'planType' => $planType,
            'gstAgreed' => $gstAgreed,
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $this->currentVendor();

        // Check if this is a bulk import from master products
        if ($request->has('selected_products') && is_array($request->selected_products)) {
            return $this->bulkStoreFromMaster($request, $vendor);
        }

        $data = $this->validateFood($request);

        $food = new VendorProduct();
        $food->id = Str::uuid()->toString();

        $this->fillFood($food, $data, $request, $vendor);

        // Clear vendor cache after creating food
        $this->clearVendorCache();

        return redirect()->route('foods')->with('success', 'Food created successfully.');
    }

    /**
     * Bulk store products from master_products to vendor_products
     */
    protected function bulkStoreFromMaster(Request $request, Vendor $vendor)
    {
        $selectedProducts = $request->input('selected_products', []);

        // Log for debugging
        \Log::info('Bulk store request received', [
            'vendor_id' => $vendor->id,
            'products_count' => count($selectedProducts),
            'products_data' => $selectedProducts
        ]);

        if (empty($selectedProducts)) {
            return redirect()->back()->with('error', 'No products selected.');
        }

        $successCount = 0;
        $errors = [];

        foreach ($selectedProducts as $index => $productData) {
            try {
                // Debug: Log each product being processed
                Log::info("Processing product #{$index}", ['data' => $productData]);

                $masterProductId = $productData['master_product_id'] ?? null;
                if (!$masterProductId) {
                    Log::warning("Product #{$index} missing master_product_id", ['data' => $productData]);
                    continue;
                }

                $masterProduct = MasterProduct::find($masterProductId);
                if (!$masterProduct) {
                    $errors[] = "Product ID {$masterProductId} not found in master products.";
                    continue;
                }

                // Check if this product already exists for this vendor
                $vendorProductId = $productData['vendor_product_id'] ?? null;

                if ($vendorProductId) {
                    $food = VendorProduct::where('vendorID', $vendor->id)
                        ->where('id', $vendorProductId)
                        ->first();
                } else {
                    $food = null;
                }
                if (!$food) {

                    // 🔒 SAFETY CHECK: prevent duplicate for same vendor + master product
                    $existingByMaster = VendorProduct::where('vendorID', $vendor->id)
                        ->where('master_product_id', $masterProduct->id)
                        ->first();

                    if ($existingByMaster) {
                        // Use existing record instead of creating duplicate
                        $food = $existingByMaster;
                    } else {
                        // Create brand new product
                        $food = new VendorProduct();
                        $food->id = Str::uuid()->toString();
                        $food->master_product_id = $masterProduct->id;
                    }
                }



                // Get vendor subscription info for price calculation using service
                $planInfo = SubscriptionPlanService::getVendorPlanInfo($vendor);
                $subscriptionPlan = $planInfo['plan'];
                $hasSubscription = $planInfo['hasSubscription'];
                $applyPercentage = $planInfo['commissionPercentage'];

                // Map master product data to vendor product
                $food->vendorID = $vendor->id;
                $food->name = $masterProduct->name;
                $food->description = $masterProduct->description ?? $masterProduct->short_description ?? '';
                $food->categoryID = $masterProduct->categoryID;
                $food->photo = $masterProduct->photo ?? null;
                $food->photos = $masterProduct->photos ? json_encode($masterProduct->photos) : null;

                // Get merchant price from input (don't fetch from master products, keep empty if not provided)
                $merchantPrice = $productData['merchant_price'] ?? null;
                // Set merchant_price directly using database column name
                if ($merchantPrice !== null && $merchantPrice !== '' && is_numeric($merchantPrice)) {
                    $food->merchant_price = number_format((float)$merchantPrice, 2, '.', '');
                } else {
                    $food->merchant_price = null;
                }

                // Get online price from input (don't calculate from master products, keep empty if not provided)
                $onlinePrice = $productData['online_price'] ?? null;
                if ($onlinePrice && is_numeric($onlinePrice)) {
                    $food->price = number_format((float)$onlinePrice, 2, '.', '');
                } else {
                    $food->price = null;
                }

                // Get discount_price from input (don't fetch from master products, keep empty if not provided)
                $discountPrice = $productData['discount_price'] ?? null;
                if ($discountPrice && is_numeric($discountPrice)) {
                    $discountPriceNum = (float)$discountPrice;
                    $onlinePriceNum = (float)($food->price ?? 0);

                    // Validate discount price is not greater than online price
                    if ($onlinePriceNum > 0 && $discountPriceNum > $onlinePriceNum) {
                        $productName = $masterProduct->name ?? 'Unknown Product';
                        $errors[] = "Discount price cannot be greater than online price for product \"{$productName}\".";
                        continue; // Skip this product
                    }

                    $food->disPrice = number_format($discountPriceNum, 2, '.', '');
                } else {
                    $food->disPrice = null;
                }

                // Handle addons
                $addOnTitles = $productData['addons_title'] ?? [];
                $addOnPrices = $productData['addons_price'] ?? [];

                // Filter out empty addons
                $filteredTitles = [];
                $filteredPrices = [];
                if (is_array($addOnTitles) && is_array($addOnPrices)) {
                    foreach ($addOnTitles as $index => $title) {
                        $price = $addOnPrices[$index] ?? null;
                        if (!empty(trim($title)) && !empty($price) && is_numeric($price)) {
                            $filteredTitles[] = trim($title);
                            $filteredPrices[] = number_format((float)$price, 2, '.', '');
                        }
                    }
                }

                if (!empty($filteredTitles) && !empty($filteredPrices)) {
                    // Store as array - Laravel will auto-convert to JSON due to model casting
                    $food->addOnsTitle = $filteredTitles;
                    $food->addOnsPrice = $filteredPrices;
                } else {
                    $food->addOnsTitle = null;
                    $food->addOnsPrice = null;
                }

                $food->nonveg = $masterProduct->nonveg ?? false;
                $food->veg = $masterProduct->veg ?? true;
                $food->quantity = -1;

// ===============================
// ✅ NORMALIZE OPTIONS (BULK CREATE)
// ===============================
                // ===============================
// ✅ STORE FULL OPTIONS (BULK CREATE)
// ===============================
                $decodedOptions = $productData['options'] ?? null;

                if (is_array($decodedOptions) && count($decodedOptions) > 0) {

                    $finalOptions = [];

                    foreach ($decodedOptions as $opt) {

                        if (is_string($opt)) {
                            $opt = json_decode($opt, true);
                        }

                        if (!empty($opt['title'])) {
                            $finalOptions[] = $opt;
                        }
                    }

                    $food->options = count($finalOptions) > 0 ? $finalOptions : null;

                } else {
                    $food->options = null; // 🔥 FORCE NULL if empty or not set
                }

                // Handle publish and isAvailable - convert string '1'/'0' to boolean
                $publishValue = $productData['publish'] ?? true;
                $food->publish = is_string($publishValue) ? ($publishValue === '1' || $publishValue === 'true') : (bool)$publishValue;

                $availableValue = $productData['isAvailable'] ?? true;
                $food->isAvailable = is_string($availableValue) ? ($availableValue === '1' || $availableValue === 'true') : (bool)$availableValue;

                $now = now()->toAtomString();
                if (!$food->createdAt) {
                    $food->createdAt = $now;
                }
                $food->updatedAt = $now;

                // Handle available_days and available_timings
                $availableDays = $productData['available_days'] ?? [];
                $availableTimings = $productData['available_timings'] ?? [];

                // Filter out empty values and ensure proper format
                $availableDays = is_array($availableDays) ? array_filter(array_values($availableDays)) : [];
                $availableDays = !empty($availableDays) ? array_values($availableDays) : null;

                // Process available_timings: Convert to restaurant working_hours format
                // Input format: available_timings[Monday][0][from] = "09:00", available_timings[Monday][0][to] = "12:00"
                // Output format: [{"day": "Monday", "timeslot": [{"from": "09:00", "to": "12:00"}]}]
                $processedTimings = [];
                if (is_array($availableTimings) && !empty($availableDays)) {
                    foreach ($availableDays as $day) {
                        if (isset($availableTimings[$day]) && is_array($availableTimings[$day])) {
                            $timeslot = [];
                            foreach ($availableTimings[$day] as $slotData) {
                                if (is_array($slotData) && !empty($slotData['from']) && !empty($slotData['to'])) {
                                    $from = trim($slotData['from']);
                                    $to = trim($slotData['to']);
                                    if (!empty($from) && !empty($to)) {
                                        $timeslot[] = ['from' => $from, 'to' => $to];
                                    }
                                }
                            }
                            if (!empty($timeslot)) {
                                $processedTimings[] = [
                                    'day' => $day,
                                    'timeslot' => $timeslot
                                ];
                            }
                        }
                    }
                }
                // Store as array - Laravel will auto-convert to JSON due to model casting (like workingHours in Vendor model)
                $food->available_days = $availableDays;
                $food->available_timings = !empty($processedTimings) ? $processedTimings : null;

                // Save to database
                \Log::info('Attempting to save product', [
                    'product_id' => $food->id,
                    'vendor_id' => $vendor->id,
                    'name' => $food->name,
                    'merchant_price' => $food->merchant_price,
                    'price' => $food->price,
                    'disPrice' => $food->disPrice,
                    'categoryID' => $food->categoryID
                ]);

                $saved = $food->save();

                if (!$saved) {
                    \Log::error('Save returned false', [
                        'product_id' => $food->id,
                        'name' => $food->name
                    ]);
                    throw new \Exception('Failed to save product to database - save() returned false');
                }

                // Verify the record exists in database
                $verify = VendorProduct::where('id', $food->id)
                    ->where('vendorID', $vendor->id)
                    ->first();

                if (!$verify) {
                    \Log::error('Product not found after save', [
                        'product_id' => $food->id,
                        'vendor_id' => $vendor->id
                    ]);
                    throw new \Exception('Product was not found in database after save operation');
                }

                \Log::info('Product saved and verified successfully', [
                    'product_id' => $food->id,
                    'name' => $food->name
                ]);

                $successCount++;

            } catch (\Exception $e) {
                $productName = isset($masterProduct) && $masterProduct ? $masterProduct->name : 'product';
                $errors[] = "Error importing {$productName}: " . $e->getMessage();
            }
        }

        $message = "Successfully imported {$successCount} product(s).";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " error(s) occurred.";
        }

        $this->clearVendorCache();

        return redirect()->route('foods')->with('success', $message)->with('import_errors', $errors);
    }

    /**
     * Get master products by category (AJAX endpoint)
     */
    public function getMasterProductsByCategory(Request $request)
    {
        $categoryId = $request->get('category_id');

        if (!$categoryId) {
            return response()->json(['success' => false, 'message' => 'Category ID is required'], 400);
        }

        $query = MasterProduct::where('categoryID', $categoryId)
            ->where('publish', true);

        // Search functionality - filter by product name only
        $search = $request->get('search');
        if ($search !== null && $search !== '' && trim((string)$search) !== '') {
            $searchTerm = trim((string)$search);
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        // Get total count before pagination
        $total = $query->count();

        // Pagination
        $perPage = (int)$request->get('per_page', 10);
        $page = (int)$request->get('page', 1);
        $perPage = max(1, min($perPage, 100)); // Limit between 1 and 100
        $page = max(1, $page);

        $products = $query->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // Get current vendor
        $vendor = $this->currentVendor();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found'
            ], 401);
        }

        // Get product names to check if they exist in vendor_products
        $productNames = $products->pluck('name')->toArray();

        // Check which products already exist for this vendor
        $existingProducts = VendorProduct::where('vendorID', $vendor->id)
            ->whereNotNull('master_product_id')
            ->select([
                'id',
                'master_product_id',
                'price',
                'merchant_price',
                'disPrice',
                'publish',
                'isAvailable',
                'options',
                'addOnsTitle',
                'addOnsPrice',
                'available_days',
                'available_timings'
            ])
            ->get()
            ->keyBy(fn ($item) => (string) $item->master_product_id);


        $lastPage = ceil($total / $perPage);

        return response()->json([
            'success' => true,
            'products' => $products->map(function ($product) use ($existingProducts) {
                $existingProduct = $existingProducts->get($product->id);


                $isExisting = $existingProduct !== null;

                $productData = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description ?? $product->short_description ?? '',
                    'photo' => $product->photo ?? null,
                    'suggested_price' => $product->suggested_price ?? $product->min_price ?? 0,
                    'nonveg' => $product->nonveg ?? false,
                    'veg' => $product->veg ?? true,
                    'is_existing' => $isExisting,
                    'options' => $this->decodeJsonField($product->options),

                    'vendor_product_id' => $existingProduct->id ?? null,
                    'merchant_price'    => $existingProduct->merchant_price ?? null,
                    'online_price'      => $existingProduct->price ?? null,
                    'discount_price'    => $existingProduct->disPrice ?? null,


                ];

                if ($isExisting && $existingProduct) {
                    $productData['vendor_options'] = $this->decodeJsonField($existingProduct->options);
                }

                // If product exists, include vendor product data
                if ($isExisting && $existingProduct) {
                    $productData['vendor_price'] = $existingProduct->price ?? null;
                    $productData['vendor_merchantPrice'] = $existingProduct->merchant_price ?? null;
                    $productData['vendor_disPrice'] = $existingProduct->disPrice ?? null;
                    $productData['vendor_publish'] = $existingProduct->publish ?? true;
                    $productData['vendor_isAvailable'] = $existingProduct->isAvailable ?? true;
                    $productData['vendor_addOnsTitle'] = $existingProduct->addOnsTitle ?? null;
                    $productData['vendor_addOnsPrice'] = $existingProduct->addOnsPrice ?? null;
                    $productData['vendor_available_days'] = $existingProduct->available_days ?? null;
                    $productData['vendor_available_timings'] = $existingProduct->available_timings ?? null;
                }

                return $productData;
            }),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ]
        ]);
    }

    public function edit($id)
    {
        $vendor = $this->currentVendor();
        // Clear vendor cache and refresh to get latest GST value
        if ($vendor) {
            $this->clearVendorCache();
            $vendor = Vendor::select(['id', 'title', 'author', 'subscriptionPlanId', 'gst'])->where('id', $vendor->id)->first();
        }
        $food = VendorProduct::where('vendorID', $vendor->id)
            ->where('id', $id)
            ->firstOrFail();
        // ✅ ADD FROM HERE
        $masterProduct = MasterProduct::where('name', $food->name)
            ->where('categoryID', $food->categoryID)
            ->first();

        $rawMasterOptions = $this->decodeJsonField($masterProduct->options ?? []);

        /*
        |--------------------------------------------------------------------------
        | FIX: Inject stable id into master options
        |--------------------------------------------------------------------------
        */
        $masterOptions = [];
        foreach ($rawMasterOptions as $index => $opt) {
            $opt['id'] = $opt['id'] ?? ('master_' . $index);
            $masterOptions[] = $opt;
        }

        $vendorOptions = $this->decodeJsonField($food->options ?? []);

        $vendorOptions = $this->decodeJsonField($food->options ?? []);

        foreach ($masterOptions as &$opt) {
            foreach ($vendorOptions as $vOpt) {

                // ✅ PRIMARY MATCH: by option id
                if (
                    isset($opt['id'], $vOpt['id']) &&
                    $opt['id'] === $vOpt['id']
                ) {
                    $opt['price'] = $vOpt['price'];
                    break;
                }

                // 🔁 BACKWARD COMPAT (old data without id)
                if (
                    !isset($vOpt['id']) &&
                    ($vOpt['title'] ?? '') === ($opt['title'] ?? '') &&
                    ($vOpt['subtitle'] ?? '') === ($opt['subtitle'] ?? '')
                ) {
                    $opt['price'] = $vOpt['price'];
                    break;
                }
            }
        }

        unset($opt);

        // ✅ ADD UNTIL HERE


        // Cache categories for 5 minutes
        $categories = Cache::remember('vendor_categories_list', 300, function () {
            return VendorCategory::orderBy('title')->get(['id', 'title']);
        });

        // Fetch subscription plan info using service
        $planInfo = SubscriptionPlanService::getVendorPlanInfo($vendor);
        $subscriptionPlan = $planInfo['plan'];
        $hasSubscription = $planInfo['hasSubscription'];
        $applyPercentage = $planInfo['commissionPercentage'];
        $planType = $planInfo['planType'];
        $gstAgreed = false;

        // Get subscription plan place percentage for settlement calculation (EDIT page only)
        $subscriptionPlacePercentage = 0;
        if ($hasSubscription && $subscriptionPlan && isset($subscriptionPlan->place)) {
            $subscriptionPlacePercentage = is_numeric($subscriptionPlan->place) ? (float)$subscriptionPlan->place : 0;
        }
        if ($vendor) {
            // Get GST agreement
            $gstAgreed = (bool)($vendor->gst ?? 0);
        }

        $addOns = $this->combineAddOns($food);
        $specifications = $this->decodeJsonField($food->product_specification);

        return view('foods.edit', [
            'food' => $food,
            'categories' => $categories,
            'addOns' => $addOns,
            'specifications' => $specifications,
            'masterOptions' => $masterOptions,
            'vendor' => $vendor,
            'placeholderImage' => $this->placeholderImage(),
            'hasSubscription' => $hasSubscription,
            'applyPercentage' => $applyPercentage,
            'planType' => $planType,
            'gstAgreed' => $gstAgreed,
            'subscriptionPlacePercentage' => $subscriptionPlacePercentage, // For settlement calculation
        ]);
    }

    public function update(Request $request, $id)
    {
        $vendor = $this->currentVendor();
        $food = VendorProduct::where('vendorID', $vendor->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $this->validateFood($request, $food->id);

        $this->fillFood($food, $data, $request, $vendor);

        // Clear vendor cache after updating food
        $this->clearVendorCache();

        return redirect()->route('foods')->with('success', 'Food updated successfully.');
    }

    public function destroy($id)
    {
        $vendor = $this->currentVendor();
        $food = VendorProduct::where('vendorID', $vendor->id)
            ->where('id', $id)
            ->firstOrFail();

        $food->delete();

        // Clear vendor cache after deleting food
        $this->clearVendorCache();

        return redirect()->route('foods')->with('success', 'Food deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $vendor = $this->currentVendor();

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string'
        ]);

        $ids = $validated['ids'];

        VendorProduct::where('vendorID', $vendor->id)
            ->whereIn('id', $ids)
            ->delete();

        // Clear vendor cache after bulk delete
        $this->clearVendorCache();

        return redirect()->route('foods')->with('success', 'Selected foods deleted successfully.');
    }

    public function togglePublish(Request $request, $id)
    {
        try {
            $vendor = $this->currentVendor();
            $food = VendorProduct::where('vendorID', $vendor->id)
                ->where('id', $id)
                ->firstOrFail();

            $current = $food->publish;
            $new = $current ? 0 : 1;

            $food->publish = $new;
            $food->updatedAt = now()->toAtomString();
            $food->save();

            // Clear vendor cache after toggle
            $this->clearVendorCache();

            return response()->json([
                'success' => true,
                'publish' => $new
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleAvailability(Request $request, $id)
    {
        $vendor = $this->currentVendor();
        $food = VendorProduct::where('vendorID', $vendor->id)
            ->where('id', $id)
            ->firstOrFail();

        $food->isAvailable = $request->boolean('isAvailable');
        $food->updatedAt = now()->toAtomString();
        $food->save();

        // Clear vendor cache after toggle
        $this->clearVendorCache();

        return back()->with('success', 'Availability updated.');
    }

    public function inlineUpdate(Request $request, $id)
    {
        try {
            $vendor = $this->currentVendor();
            $field = $request->input('field');
            $value = $request->input('value');

            if (!in_array($field, ['price', 'disPrice'])) {
                return response()->json(['success' => false, 'message' => 'Invalid field. Only price and disPrice are allowed.'], 400);
            }

            if (!is_numeric($value) || $value < 0) {
                return response()->json(['success' => false, 'message' => 'Invalid price value. Price must be a positive number.'], 400);
            }

            if ($value > 999999) {
                return response()->json(['success' => false, 'message' => 'Price cannot exceed 999,999'], 400);
            }

            $food = VendorProduct::where('vendorID', $vendor->id)
                ->where('id', $id)
                ->firstOrFail();

            $formattedValue = number_format((float)$value, 2, '.', '');
            $food->{$field} = $formattedValue;

            if ($field === 'price' && (float)$food->disPrice > (float)$formattedValue) {
                $food->disPrice = '0';
            }

            $food->updatedAt = now()->toAtomString();
            $food->save();

            // Clear vendor cache after inline update
            $this->clearVendorCache();

            return response()->json([
                'success' => true,
                'message' => 'Price updated successfully.',
                'data' => [
                    'field' => $field,
                    'value' => $formattedValue
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Update failed. Please check your input and try again.'
            ], 400);
        }
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'name', 'price', 'description', 'vendorID', 'categoryID',
            'disPrice', 'publish', 'nonveg', 'isAvailable', 'photo'
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $sampleData = [
            'Sample Food Item', 10.99, 'This is a sample food description',
            'vendor_id_here', 'category_id_here', 8.99, 1, 0, 1, 'photo_url_here'
        ];

        foreach ($sampleData as $index => $value) {
            $sheet->setCellValueByColumnAndRow($index + 1, 2, $value);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'food_import_template.xlsx';
        $path = storage_path('app/temp/' . $filename);

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend();
    }

    public function import(Request $request)
    {
        try {
            $vendor = $this->currentVendor();

            $request->validate([
                'file' => 'required|file|mimes:xls,xlsx|max:2048'
            ]);

            $file = $request->file('file');
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $headers = array_shift($rows);

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            $chunkSize = 50;
            $chunks = array_chunk($rows, $chunkSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                if (connection_aborted()) {
                    break;
                }

                foreach ($chunk as $rowIndex => $row) {
                    $actualIndex = ($chunkIndex * $chunkSize) + $rowIndex;

                    try {
                        if (empty(array_filter($row))) {
                            continue;
                        }

                        $data = array_combine($headers, $row);

                        if (empty($data['name']) || empty($data['price'])) {
                            $errors[] = "Row " . ($actualIndex + 2) . ": Name and price are required";
                            $errorCount++;
                            continue;
                        }

                        $foodData = [
                            'id' => Str::uuid()->toString(),
                            'name' => $data['name'],
                            'price' => number_format((float)$data['price'], 2, '.', ''),
                            'description' => $data['description'] ?? '',
                            'disPrice' => number_format((float)($data['disPrice'] ?? 0), 2, '.', ''),
                            'publish' => !empty($data['publish']),
                            'nonveg' => !empty($data['nonveg']),
                            'veg' => empty($data['nonveg']),
                            'isAvailable' => array_key_exists('isAvailable', $data) ? (bool)$data['isAvailable'] : true,
                            'photo' => $data['photo'] ?? '',
                            'createdAt' => now()->toAtomString(),
                            'updatedAt' => now()->toAtomString(),
                            'vendorID' => !empty($data['vendorID']) ? $data['vendorID'] : $vendor->id,
                            'categoryID' => $data['categoryID'] ?? null,
                        ];

                        if (empty($foodData['categoryID'])) {
                            $errors[] = "Row " . ($actualIndex + 2) . ": categoryID is required.";
                            $errorCount++;
                            continue;
                        }

                        VendorProduct::create($foodData);
                        $successCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Row " . ($actualIndex + 2) . ": " . $e->getMessage();
                        $errorCount++;
                    }
                }
            }

            $message = "Import completed. Successfully imported {$successCount} items.";
            if ($errorCount > 0) {
                $message .= " {$errorCount} items failed to import.";
            }

            return redirect()->back()
                ->with('success', $message)
                ->with('import_errors', $errors);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    protected function validateFood(Request $request, ?string $foodId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'merchant_price' => 'nullable|numeric|min:0',
            'online_price' => 'required|numeric|min:0',
            'price' => 'nullable|numeric|min:0', // Keep for backward compatibility
            'disPrice' => 'nullable|numeric|min:0',
            'categoryID' => 'required|string|exists:vendor_categories,id',
            'description' => 'required|string',
            'quantity' => 'nullable|integer|min:-1',
            'photo_upload' => 'nullable|image|max:4096',
            'photo_url' => 'nullable|url',
            'gallery_uploads.*' => 'nullable|image|max:4096',
            'gallery_urls' => 'nullable|string',
            'add_ons_title' => 'nullable|array',
            'add_ons_title.*' => 'nullable|string|max:255',
            'add_ons_price' => 'nullable|array',
            'add_ons_price.*' => 'nullable|numeric|min:0',
            'specification_label' => 'nullable|array',
            'specification_label.*' => 'nullable|string|max:255',
            'specification_value' => 'nullable|array',
            'specification_value.*' => 'nullable|string|max:255',
            'available_days' => 'nullable|array',
            'available_days.*' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'available_timings' => 'nullable|array',
            'available_timings.*' => 'nullable|array',
            'available_timings.*.*' => 'nullable|array',
            'available_timings.*.*.from' => 'nullable|date_format:H:i',
            'available_timings.*.*.to' => 'nullable|date_format:H:i',
        ];

        // If online_price is provided, use it; otherwise fallback to price
        $validated = $request->validate($rules, [
            'photo_upload.max' => 'The photo upload must not be greater than 4mb.',
            'gallery_uploads.*.max' => 'The gallery uploads must not be greater than 4mb.',
        ]);

        // Ensure online_price is set (use price as fallback for backward compatibility)
        if (empty($validated['online_price']) && !empty($validated['price'])) {
            $validated['online_price'] = $validated['price'];
        }

        // Validate discount price is not greater than online price
        if (!empty($validated['disPrice']) && !empty($validated['online_price'])) {
            $discountPrice = (float)$validated['disPrice'];
            $onlinePrice = (float)$validated['online_price'];

            if ($discountPrice > $onlinePrice) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'disPrice' => 'Discount price cannot be greater than online price.'
                ]);
            }
        }

        // Validate time slot format manually (from/to format)
        if (!empty($validated['available_timings']) && is_array($validated['available_timings'])) {
            foreach ($validated['available_timings'] as $day => $slots) {
                if (is_array($slots)) {
                    foreach ($slots as $index => $slotData) {
                        if (is_array($slotData)) {
                            $from = $slotData['from'] ?? '';
                            $to = $slotData['to'] ?? '';
                            if (!empty($from) || !empty($to)) {
                                // If either is provided, both should be provided
                                if (empty($from) || empty($to)) {
                                    throw \Illuminate\Validation\ValidationException::withMessages([
                                        "available_timings.{$day}.{$index}" => "Both 'from' and 'to' times are required for {$day} time slot."
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $validated;
    }

    protected function fillFood(VendorProduct $food, array $data, Request $request, Vendor $vendor): void
    {
        $now = now()->toAtomString();

        $food->vendorID = $vendor->id;
        $food->name = $data['name'];

        // Handle merchant price (don't fetch from master products, keep empty if not provided)
        $merchantPrice = $request->input('merchant_price') ?? $data['merchant_price'] ?? null;
        // Set merchant_price directly using database column name
        if ($merchantPrice !== null && $merchantPrice !== '' && is_numeric($merchantPrice)) {
            $food->merchant_price = number_format((float)$merchantPrice, 2, '.', '');
        } else {
            $food->merchant_price = null;
        }

        // Handle online price: use manual value if provided, otherwise calculate from merchant price
        $onlinePrice = $request->input('online_price') ?? $data['online_price'] ?? null;

        // Check if online price is manually provided (and > 0)
        if ($onlinePrice !== null && $onlinePrice !== '' && is_numeric($onlinePrice)) {
            $onlinePriceNum = (float)$onlinePrice;
            if ($onlinePriceNum > 0) {
                // Manual override: use the provided value
                $food->price = number_format($onlinePriceNum, 2, '.', '');
            } elseif ($onlinePriceNum < 0) {
                // Invalid negative price
                $food->price = null;
            } else {
                // onlinePriceNum is 0 - treat as empty, calculate from merchant price if available
                if ($merchantPrice !== null && $merchantPrice !== '' && is_numeric($merchantPrice)) {
                    // Calculate online price from merchant price using service
                    $planInfo = SubscriptionPlanService::getVendorPlanInfo($vendor);
                    $hasSubscription = $planInfo['hasSubscription'];
                    $applyPercentage = $planInfo['commissionPercentage'];
                    $planType = $planInfo['planType'];

                    $gstAgreed = (bool)($vendor->gst ?? 0);
                    $priceResult = PricingCalculationService::calculatePrice(
                        (float)$merchantPrice,
                        $hasSubscription,
                        $applyPercentage,
                        $gstAgreed,
                        $planType
                    );
                    $food->price = number_format($priceResult['onlinePrice'], 2, '.', '');
                } else {
                    $food->price = null;
                }
            }
        } elseif ($merchantPrice !== null && $merchantPrice !== '' && is_numeric($merchantPrice)) {
            // Online price not provided but merchant price exists - calculate online price using service
            $planInfo = SubscriptionPlanService::getVendorPlanInfo($vendor);
            $hasSubscription = $planInfo['hasSubscription'];
            $applyPercentage = $planInfo['commissionPercentage'];
            $planType = $planInfo['planType'];

            $gstAgreed = (bool)($vendor->gst ?? 0);
            $priceResult = PricingCalculationService::calculatePrice(
                (float)$merchantPrice,
                $hasSubscription,
                $applyPercentage,
                $gstAgreed,
                $planType
            );
            $food->price = number_format($priceResult['onlinePrice'], 2, '.', '');
        } else {
            // Both are empty/null
            $food->price = null;
        }

        // Handle discount price (don't fetch from master products, keep empty if not provided)
        $discountPrice = $request->input('discount_price') ?? $data['disPrice'] ?? null;
        if ($discountPrice && is_numeric($discountPrice)) {
            $discountPriceNum = (float)$discountPrice;
            $onlinePriceNum = (float)($food->price ?? 0);

            // Validate discount price is not greater than online price
            if ($onlinePriceNum > 0 && $discountPriceNum > $onlinePriceNum) {
                $productName = $food->name ?? 'this product';
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'disPrice' => "Discount price cannot be greater than online price for product \"{$productName}\"."
                ]);
            }

            $food->disPrice = number_format($discountPriceNum, 2, '.', '');
        } else {
            $food->disPrice = null;
        }
        $food->description = $data['description'];
        $food->categoryID = $data['categoryID'];
        $food->quantity = $data['quantity'] ?? -1;
        $food->publish = $request->boolean('publish');
        $food->isAvailable = $request->boolean('isAvailable');
        $food->nonveg = $request->boolean('nonveg');
        $food->veg = !$food->nonveg;

        if (!$food->createdAt) {
            $food->createdAt = $now;
        }
        $food->updatedAt = $now;

        [$addOnTitles, $addOnPrices] = $this->prepareAddOns($request);
        // Store as array - Laravel will auto-convert to JSON due to model casting
        $food->addOnsTitle = $addOnTitles ? $addOnTitles : null;
        $food->addOnsPrice = $addOnPrices ? $addOnPrices : null;

        $specifications = $this->prepareSpecifications($request);
        $food->product_specification = !empty($specifications) ? json_encode($specifications) : null;

        // Handle available_days and available_timings
        $availableDays = $request->input('available_days', []);
        $availableTimings = $request->input('available_timings', []);

        // Filter out empty values and ensure proper format
        $availableDays = is_array($availableDays) ? array_filter(array_values($availableDays)) : [];
        $availableDays = !empty($availableDays) ? array_values($availableDays) : null;

        // Process available_timings: Convert to restaurant working_hours format
        // Input format: available_timings[Monday][0][from] = "09:00", available_timings[Monday][0][to] = "12:00"
        // Output format: [{"day": "Monday", "timeslot": [{"from": "09:00", "to": "12:00"}]}]
        $processedTimings = [];
        if (is_array($availableTimings) && !empty($availableDays)) {
            foreach ($availableDays as $day) {
                if (isset($availableTimings[$day]) && is_array($availableTimings[$day])) {
                    $timeslot = [];
                    foreach ($availableTimings[$day] as $slotData) {
                        if (is_array($slotData) && !empty($slotData['from']) && !empty($slotData['to'])) {
                            $from = trim($slotData['from']);
                            $to = trim($slotData['to']);
                            if (!empty($from) && !empty($to)) {
                                $timeslot[] = ['from' => $from, 'to' => $to];
                            }
                        }
                    }
                    if (!empty($timeslot)) {
                        $processedTimings[] = [
                            'day' => $day,
                            'timeslot' => $timeslot
                        ];
                    }
                }
            }
        }
        // Store as array - Laravel will auto-convert to JSON due to model casting (like workingHours in Vendor model)
        $food->available_days = $availableDays;
        $food->available_timings = !empty($processedTimings) ? $processedTimings : null;

        // Ensure photo is a string (handle potential array from database)
        $currentPhoto = $food->photo;
        if (is_array($currentPhoto)) {
            // If it's an array, try to get the first string value
            $currentPhoto = null;
            array_walk_recursive($food->photo, function ($value) use (&$currentPhoto) {
                if (is_string($value) && !empty(trim($value)) && $currentPhoto === null) {
                    $currentPhoto = $value;
                }
            });
        }
        $mainPhoto = $this->determineMainPhoto($request, is_string($currentPhoto) ? $currentPhoto : null);
        $food->photo = $mainPhoto;

        $gallery = $this->buildGallery($request, $mainPhoto, $food);
        // Limit gallery to prevent database overflow - keep only first 10 photos
        $gallery = array_slice($gallery, 0, 10);
        $food->photos = !empty($gallery) ? json_encode($gallery, JSON_UNESCAPED_SLASHES) : null;

        // ===============================
// SAVE PRODUCT OPTIONS
// ===============================

        $optionsJson = $request->input('options_json');

        $food->options = null;

        if (!empty($optionsJson)) {

            $decodedOptions = json_decode($optionsJson, true);

            if (is_array($decodedOptions) && count($decodedOptions) > 0) {

                $finalOptions = [];

                foreach ($decodedOptions as $opt) {

                    // Ignore invalid options
                    if (!is_array($opt)) {
                        continue;
                    }

                    $title = trim($opt['title'] ?? '');
                    $price = $opt['price'] ?? null;

                    // Skip if title empty
                    if ($title === '') {
                        continue;
                    }

                    // Skip if price empty or zero (means not selected)
                    if ($price === null || $price === '' || floatval($price) <= 0) {
                        continue;
                    }

                    $finalOptions[] = $opt;
                }

                if (!empty($finalOptions)) {
                    $food->options = $finalOptions;
                }
            }
        }


        $food->save();
    }

    protected function determineMainPhoto(Request $request, ?string $current): ?string
    {
        if ($request->boolean('remove_photo')) {
            // Delete old photo from Firebase Storage if it exists
            if ($current) {
                $this->deleteFileIfFirebase($current);
            }
            $current = null;
        }

        if ($request->hasFile('photo_upload')) {
            // Delete old photo from Firebase Storage if it exists
            if ($current) {
                $this->deleteFileIfFirebase($current);
            }
            return $this->storeImage($request->file('photo_upload'));
        }

        if ($url = $request->input('photo_url')) {
            // Delete old photo from Firebase Storage if it exists
            if ($current) {
                $this->deleteFileIfFirebase($current);
            }
            return $url;
        }

        return $current;
    }

    protected function buildGallery(Request $request, ?string $mainPhoto, VendorProduct $food): array
    {
        $gallery = [];

        // Start with main photo if provided
        if ($mainPhoto && is_string($mainPhoto)) {
            $gallery[] = $mainPhoto;
        }

        // Add new gallery uploads
        if ($request->hasFile('gallery_uploads')) {
            foreach ($request->file('gallery_uploads') as $file) {
                if ($file instanceof UploadedFile) {
                    $gallery[] = $this->storeImage($file);
                }
            }
        }

        // Add gallery URLs
        $gallery = array_merge($gallery, $this->parseGalleryUrls($request->input('gallery_urls')));

        // Delete old photos from Firebase Storage (except main photo if it hasn't changed)
        $existing = $this->decodeJsonField($food->photos);
        foreach ($existing as $photo) {
            // Don't delete if it's the main photo and main photo hasn't changed
            if (is_string($photo) && $photo !== $mainPhoto && $photo !== $food->photo) {
                $this->deleteFileIfFirebase($photo);
            }
        }

        // Ensure all gallery items are strings before returning
        $gallery = array_values(array_filter(array_unique($gallery), function ($item) {
            return is_string($item) && !empty(trim($item));
        }));

        return $gallery;
    }

    protected function parseGalleryUrls(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        $urls = preg_split("/\r\n|\n|\r/", $raw);

        return array_values(array_filter(array_map('trim', $urls)));
    }

    protected function prepareAddOns(Request $request): array
    {
        $titles = $request->input('add_ons_title', []);
        $prices = $request->input('add_ons_price', []);

        $preparedTitles = [];
        $preparedPrices = [];

        $count = max(count($titles), count($prices));

        for ($i = 0; $i < $count; $i++) {
            $title = trim($titles[$i] ?? '');
            $price = $prices[$i] ?? null;

            if ($title === '' || $price === null || $price === '') {
                continue;
            }

            $preparedTitles[] = $title;
            $preparedPrices[] = number_format((float)$price, 2, '.', '');
        }

        return [$preparedTitles, $preparedPrices];
    }

    protected function prepareSpecifications(Request $request): array
    {
        $labels = $request->input('specification_label', []);
        $values = $request->input('specification_value', []);

        $specifications = [];

        $count = max(count($labels), count($values));

        for ($i = 0; $i < $count; $i++) {
            $label = trim($labels[$i] ?? '');
            $value = trim($values[$i] ?? '');

            if ($label === '' || $value === '') {
                continue;
            }

            $specifications[$label] = $value;
        }

        return $specifications;
    }

    protected function combineAddOns(VendorProduct $food): array
    {
        $titles = $this->decodeJsonField($food->addOnsTitle);
        $prices = $this->decodeJsonField($food->addOnsPrice);

        $items = [];
        $count = max(count($titles), count($prices));

        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'title' => $titles[$i] ?? '',
                'price' => $prices[$i] ?? '',
            ];
        }

        return $items;
    }

    protected function decodeJsonField($value, $default = [])
    {
        if (is_array($value)) {
            return $value;
        }

        if (empty($value)) {
            return $default;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return $default;
    }

    protected function storeImage(UploadedFile $file): string
    {
        // Compress image if larger than 2MB
        $fileToUpload = $this->compressImageIfNeeded($file);

        // Upload to Firebase Storage
        return $this->firebaseStorage->uploadFile(
            $fileToUpload,
            'vendor_products/product_' . time() . '_' . uniqid() . '.' . $fileToUpload->getClientOriginalExtension()
        );
    }

    /**
     * Compress image if it's larger than 2MB
     *
     * @param UploadedFile $file
     * @return UploadedFile
     */
    protected function compressImageIfNeeded(UploadedFile $file): UploadedFile
    {
        $maxSize = 2 * 1024 * 1024; // 2MB in bytes

        // Only compress if file is larger than 2MB
        if ($file->getSize() <= $maxSize) {
            return $file;
        }

        // Check if it's an image
        if (!in_array($file->getMimeType(), ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])) {
            return $file;
        }

        try {
            // Create image resource
            $image = null;
            $mimeType = $file->getMimeType();

            if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
                $image = imagecreatefromjpeg($file->getPathname());
            } elseif ($mimeType === 'image/png') {
                $image = imagecreatefrompng($file->getPathname());
            } elseif ($mimeType === 'image/webp') {
                $image = imagecreatefromwebp($file->getPathname());
            }

            if (!$image) {
                return $file; // Return original if we can't process
            }

            // Get original dimensions
            $width = imagesx($image);
            $height = imagesy($image);

            // Calculate new dimensions (max 1920px on longest side, maintain aspect ratio)
            $maxDimension = 1920;
            if ($width > $height) {
                if ($width > $maxDimension) {
                    $newWidth = $maxDimension;
                    $newHeight = (int)($height * ($maxDimension / $width));
                } else {
                    $newWidth = $width;
                    $newHeight = $height;
                }
            } else {
                if ($height > $maxDimension) {
                    $newHeight = $maxDimension;
                    $newWidth = (int)($width * ($maxDimension / $height));
                } else {
                    $newWidth = $width;
                    $newHeight = $height;
                }
            }

            // Create new image with new dimensions
            $compressed = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG
            if ($mimeType === 'image/png') {
                imagealphablending($compressed, false);
                imagesavealpha($compressed, true);
                $transparent = imagecolorallocatealpha($compressed, 255, 255, 255, 127);
                imagefilledrectangle($compressed, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Resize image
            imagecopyresampled($compressed, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Save compressed image to temporary file
            $tempPath = sys_get_temp_dir() . '/' . uniqid('compressed_') . '.' . $file->getClientOriginalExtension();

            $quality = 85; // Start with 85% quality
            $saved = false;

            // Try different quality levels until file is under 2MB
            while ($quality >= 50 && !$saved) {
                if ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
                    $saved = imagejpeg($compressed, $tempPath, $quality);
                } elseif ($mimeType === 'image/png') {
                    // PNG compression level (0-9, 9 is highest compression)
                    $pngQuality = (int)((100 - $quality) / 11.11);
                    $saved = imagepng($compressed, $tempPath, $pngQuality);
                } elseif ($mimeType === 'image/webp') {
                    $saved = imagewebp($compressed, $tempPath, $quality);
                }

                if ($saved && file_exists($tempPath)) {
                    $compressedSize = filesize($tempPath);
                    if ($compressedSize <= $maxSize) {
                        break; // File is now under 2MB
                    }
                    // If still too large, reduce quality
                    $quality -= 5;
                    if ($quality < 50) {
                        break; // Don't go below 50% quality
                    }
                } else {
                    break;
                }
            }

            // Clean up
            imagedestroy($image);
            imagedestroy($compressed);

            if ($saved && file_exists($tempPath)) {
                // Create new UploadedFile from compressed image
                $compressedFile = new UploadedFile(
                    $tempPath,
                    $file->getClientOriginalName(),
                    $file->getMimeType(),
                    null,
                    true // test mode
                );

                return $compressedFile;
            }

            // If compression failed, return original
            return $file;
        } catch (\Exception $e) {
            // Log error but don't fail - return original file
            \Log::warning('Image compression failed: ' . $e->getMessage());
            return $file;
        }
    }

    protected function currentVendor(): Vendor
    {
        return $this->getCachedVendor();
    }

    protected function placeholderImage(): string
    {
        // Cache placeholder image URL for 5 minutes
        // Use a more specific cache key and optimize the query
        return Cache::remember('placeholder_image_url', 300, function () {
            // Optimize: Select only the fields column instead of entire record
            $setting = Setting::select('fields')
                ->where('document_name', 'placeHolderImage')
                ->first();

            if (!$setting || !$setting->fields) {
                return asset('assets/images/placeholder.png');
            }

            $fields = is_array($setting->fields)
                ? $setting->fields
                : json_decode($setting->fields, true);

            $url = $fields['image'] ?? null;

            return $url ?: asset('assets/images/placeholder.png');
        });
    }

    /**
     * Delete file from Firebase Storage if it's a Firebase Storage URL
     *
     * @param string|null $url
     * @return void
     */
    protected function deleteFileIfFirebase(?string $url): void
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

    /**
     * Clear cached vendor data
     * Call this when vendor-related data changes
     */
    protected function clearVendorCache(): void
    {
        $userId = Auth::id();
        Cache::forget("current_vendor_{$userId}");
    }

    /**
     * Clear categories cache
     * Call this when categories are updated
     */
    protected function clearCategoriesCache(): void
    {
        Cache::forget('vendor_categories_list');
    }

    /**
     * Recalculate all product prices for current vendor based on subscription status
     * This can be called manually or automatically when subscription changes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recalculatePrices(Request $request)
    {
        try {
            $vendor = $this->currentVendor();

            if (!$vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor not found.'
                ], 404);
            }

            $updatedCount = $this->recalculateProductPrices($vendor);

            return response()->json([
                'success' => true,
                'message' => "Successfully recalculated prices for {$updatedCount} product(s).",
                'products_updated' => $updatedCount
            ]);
        } catch (\Exception $e) {
            \Log::error("Error in recalculatePrices endpoint: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate prices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate all product prices for a vendor based on subscription status
     * This is called when subscription is added, removed, or changed
     *
     * @param Vendor $vendor
     * @return int Number of products updated
     */
    public function recalculateProductPrices(Vendor $vendor): int
    {
        try {
            // Refresh vendor to get latest subscription status from database
            $vendor->refresh();

            // Get subscription info using service
            $planInfo = SubscriptionPlanService::getVendorPlanInfo($vendor);
            $hasSubscription = $planInfo['hasSubscription'];
            $applyPercentage = $planInfo['commissionPercentage'];
            $planType = $planInfo['planType'];
            $gstAgreed = (bool)($vendor->gst ?? 0);

            // Get all products for this vendor that have merchant_price
            $products = VendorProduct::where('vendorID', $vendor->id)
                ->whereNotNull('merchant_price')
                ->where('merchant_price', '>', 0)
                ->get();

            $updatedCount = 0;
            $now = now()->toAtomString();

            foreach ($products as $product) {
                $merchantPrice = (float)($product->merchant_price ?? 0);

                if ($merchantPrice <= 0) {
                    continue; // Skip if no merchant price
                }

                // Calculate new online price using PricingCalculationService (respects planType and GST)
                $priceResult = PricingCalculationService::calculatePrice(
                    $merchantPrice,
                    $hasSubscription && $planType === 'subscription', // Only true if subscription plan (not commission)
                    $applyPercentage,
                    $gstAgreed,
                    $planType
                );

                $newOnlinePrice = $priceResult['onlinePrice'];

                // Update only if price has changed
                $currentOnlinePrice = (float)($product->price ?? 0);
                if (abs($currentOnlinePrice - $newOnlinePrice) > 0.01) {
                    $product->price = number_format($newOnlinePrice, 2, '.', '');

                    // If discount price is greater than new online price, reset it
                    $discountPrice = (float)($product->disPrice ?? 0);
                    if ($discountPrice > $newOnlinePrice) {
                        $product->disPrice = null;
                    }

                    $product->updatedAt = $now;
                    $product->save();
                    $updatedCount++;
                }
            }

            // Clear vendor cache after recalculation
            $this->clearVendorCache();

            \Log::info("Recalculated prices for vendor {$vendor->id}", [
                'vendor_id' => $vendor->id,
                'has_subscription' => $hasSubscription,
                'apply_percentage' => $applyPercentage,
                'products_updated' => $updatedCount
            ]);

            return $updatedCount;
        } catch (\Exception $e) {
            \Log::error("Error recalculating product prices for vendor {$vendor->id}: " . $e->getMessage());
            return 0;
        }
    }
}
