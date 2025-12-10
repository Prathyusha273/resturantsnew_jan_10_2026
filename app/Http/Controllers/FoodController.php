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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        $query = VendorProduct::with('category')
            ->where('vendorID', $vendor->id);

        if ($search = trim((string) $request->get('search'))) {
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

        $categories = VendorCategory::orderBy('title')->get(['id', 'title']);

        return view('foods.index', [
            'foods' => $foods,
            'categories' => $categories,
            'vendor' => $vendor,
            'placeholderImage' => $this->placeholderImage(),
        ]);
    }

    public function create()
    {
        $vendor = $this->currentVendor();
        $categories = VendorCategory::orderBy('title')->get(['id', 'title']);

        return view('foods.create', [
            'categories' => $categories,
            'vendor' => $vendor,
            'placeholderImage' => $this->placeholderImage(),
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $this->currentVendor();
        $data = $this->validateFood($request);

        $food = new VendorProduct();
        $food->id = Str::uuid()->toString();

        $this->fillFood($food, $data, $request, $vendor);

        return redirect()->route('foods')->with('success', 'Food created successfully.');
    }

    public function edit($id)
    {
        $vendor = $this->currentVendor();
        $food = VendorProduct::where('vendorID', $vendor->id)
            ->where('id', $id)
            ->firstOrFail();

        $categories = VendorCategory::orderBy('title')->get(['id', 'title']);

        $extraPhotos = $this->decodeJsonField($food->photos);
        $addOns = $this->combineAddOns($food);
        $specifications = $this->decodeJsonField($food->product_specification);

        return view('foods.edit', [
            'food' => $food,
            'categories' => $categories,
            'extraPhotos' => $extraPhotos,
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

        return redirect()->route('foods')->with('success', 'Food updated successfully.');
    }

    public function destroy($id)
    {
        $vendor = $this->currentVendor();
        $food = VendorProduct::where('vendorID', $vendor->id)
            ->where('id', $id)
            ->firstOrFail();

        $food->delete();

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

        return redirect()->route('foods')->with('success', 'Selected foods deleted successfully.');
    }

    public function togglePublish(Request $request, $id)
    {
        $vendor = $this->currentVendor();
        $food = VendorProduct::where('vendorID', $vendor->id)
            ->where('id', $id)
            ->firstOrFail();

        $food->publish = $request->boolean('publish');
        $food->updatedAt = now()->toAtomString();
        $food->save();

        return back()->with('success', 'Publish status updated.');
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

            $formattedValue = number_format((float) $value, 2, '.', '');
            $food->{$field} = $formattedValue;

            if ($field === 'price' && (float) $food->disPrice > (float) $formattedValue) {
                $food->disPrice = '0';
            }

            $food->updatedAt = now()->toAtomString();
            $food->save();

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
                            'price' => number_format((float) $data['price'], 2, '.', ''),
                            'description' => $data['description'] ?? '',
                            'disPrice' => number_format((float) ($data['disPrice'] ?? 0), 2, '.', ''),
                            'publish' => !empty($data['publish']),
                            'nonveg' => !empty($data['nonveg']),
                            'veg' => empty($data['nonveg']),
                            'isAvailable' => array_key_exists('isAvailable', $data) ? (bool) $data['isAvailable'] : true,
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
        ]);
    }

    protected function fillFood(VendorProduct $food, array $data, Request $request, Vendor $vendor): void
    {
        $now = now()->toAtomString();

        $food->vendorID = $vendor->id;
        $food->name = $data['name'];
        $food->price = number_format((float) $data['price'], 2, '.', '');
        $food->disPrice = isset($data['disPrice']) ? number_format((float) $data['disPrice'], 2, '.', '') : '0';
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

        $mainPhoto = $this->determineMainPhoto($request, $food->photo);
        $food->photo = $mainPhoto;

        $gallery = $this->buildGallery($request, $mainPhoto, $food);
        $food->photos = !empty($gallery) ? json_encode($gallery) : null;

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
        $existing = $this->decodeJsonField($food->photos);
        $keep = $request->input('keep_photos', []);

        // Delete removed photos from Firebase Storage
        foreach ($existing as $photo) {
            if (!in_array($photo, (array) $keep)) {
                $this->deleteFileIfFirebase($photo);
            }
        }

        $gallery = array_values(array_filter($existing, function ($photo) use ($keep) {
            return in_array($photo, (array) $keep);
        }));

        if ($mainPhoto) {
            $gallery = array_values(array_filter($gallery, fn($photo) => $photo !== $mainPhoto));
            array_unshift($gallery, $mainPhoto);
        }

        if ($request->hasFile('gallery_uploads')) {
            foreach ($request->file('gallery_uploads') as $file) {
                if ($file instanceof UploadedFile) {
                    $gallery[] = $this->storeImage($file);
                }
            }
        }

        $gallery = array_merge($gallery, $this->parseGalleryUrls($request->input('gallery_urls')));

        return array_values(array_filter(array_unique($gallery)));
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
        // Upload to Firebase Storage
        return $this->firebaseStorage->uploadFile(
            $file,
            'vendor_products/product_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension()
        );
    }

    protected function currentVendor(): Vendor
    {
        $user = Auth::user();

        if ($user && $user->vendorID) {
            $vendor = Vendor::where('id', $user->vendorID)->first();
            if ($vendor) {
                return $vendor;
            }
        }

        $vendor = Vendor::where('author', Auth::id())->first();

        if (!$vendor) {
            abort(403, 'Vendor profile not found.');
        }

        return $vendor;
    }

    protected function placeholderImage(): string
    {
        $fields = Setting::getFields('placeHolderImage');
        $url = $fields['image'] ?? null;

        return $url ?: asset('assets/images/placeholder.png');
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
}

