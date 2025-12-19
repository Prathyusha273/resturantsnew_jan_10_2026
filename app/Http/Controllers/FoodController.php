<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Vendor;
use App\Models\VendorCategory;
use App\Models\VendorProduct;
use App\Services\FirebaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        // Select only needed columns to reduce memory usage
        $query = VendorProduct::with(['category:id,title'])
            ->select([
                'id', 'name', 'description', 'price', 'disPrice', 'photo',
                'publish', 'isAvailable', 'categoryID', 'updatedAt'
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

        // Format updatedAt dates in controller instead of view
        $foods->transform(function ($food) {
            $food->formattedUpdatedAt = $food->updatedAt
                ? Carbon::parse($food->updatedAt)
                    ->timezone('Asia/Kolkata')
                    ->format('M d, Y H:i')
                : '—';
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
        ]);
    }

    public function create()
    {
        // Cache categories for 5 minutes - optimized query (select only needed columns)
        $categories = Cache::remember('vendor_categories_list', 300, function () {
            return VendorCategory::select(['id', 'title'])
                ->orderBy('title')
                ->get();
        });

        // Cache placeholder image for 5 minutes
        $placeholderImage = $this->placeholderImage();

        return view('foods.create', [
            'categories' => $categories,
            'placeholderImage' => $placeholderImage,
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $this->currentVendor();
        $data = $this->validateFood($request);

        $food = new VendorProduct();
        $food->id = Str::uuid()->toString();

        $this->fillFood($food, $data, $request, $vendor);

        // Clear vendor cache after creating food
        $this->clearVendorCache();

        return redirect()->route('foods')->with('success', 'Food created successfully.');
    }

    public function edit($id)
    {
        $vendor = $this->currentVendor();
        $food = VendorProduct::where('vendorID', $vendor->id)
            ->where('id', $id)
            ->firstOrFail();

        // Cache categories for 5 minutes
        $categories = Cache::remember('vendor_categories_list', 300, function () {
            return VendorCategory::orderBy('title')->get(['id', 'title']);
        });

        $addOns = $this->combineAddOns($food);
        $specifications = $this->decodeJsonField($food->product_specification);

        return view('foods.edit', [
            'food' => $food,
            'categories' => $categories,
            'addOns' => $addOns,
            'specifications' => $specifications,
            'vendor' => $vendor,
            'placeholderImage' => $this->placeholderImage(),
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
        return $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'disPrice' => 'nullable|numeric|min:0|lte:price',
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
        ], [
            'photo_upload.max' => 'The photo upload must not be greater than 4mb.',
            'gallery_uploads.*.max' => 'The gallery uploads must not be greater than 4mb.',
        ]);
    }

    protected function fillFood(VendorProduct $food, array $data, Request $request, Vendor $vendor): void
    {
        $now = now()->toAtomString();

        $food->vendorID = $vendor->id;
        $food->name = $data['name'];
        $food->price = number_format((float)$data['price'], 2, '.', '');
        $food->disPrice = isset($data['disPrice']) ? number_format((float)$data['disPrice'], 2, '.', '') : '0';
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
        $food->addOnsTitle = $addOnTitles ? json_encode($addOnTitles) : null;
        $food->addOnsPrice = $addOnPrices ? json_encode($addOnPrices) : null;

        $specifications = $this->prepareSpecifications($request);
        $food->product_specification = !empty($specifications) ? json_encode($specifications) : null;

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
            $preparedPrices[] = number_format((float) $price, 2, '.', '');
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
                    $newHeight = (int) ($height * ($maxDimension / $width));
                } else {
                    $newWidth = $width;
                    $newHeight = $height;
                }
            } else {
                if ($height > $maxDimension) {
                    $newHeight = $maxDimension;
                    $newWidth = (int) ($width * ($maxDimension / $height));
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
                    $pngQuality = (int) ((100 - $quality) / 11.11);
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
}

