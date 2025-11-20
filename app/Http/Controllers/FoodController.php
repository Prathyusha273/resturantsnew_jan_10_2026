<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\VendorUsers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FoodController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
        $this->middleware('auth');
    }

	  public function index()
    {
      $user = Auth::user();
      $id = Auth::id();
      $exist = VendorUsers::where('firebase_id',$id)->first();
      $id=$exist->uuid;

   		return view("foods.index")->with('id',$id);
    }

    public function edit($id)
    {
    	return view('foods.edit')->with('id',$id);
    }

    public function create()
    {
      $user = Auth::user();
      $id = Auth::id();
      $exist = VendorUsers::where('user_id',$id)->first();
      $id=$exist->uuid;

      return view('foods.create')->with('id',$id);
    }

    /**
     * Handle inline updates for food prices and discount prices
     * This method only validates the data - the frontend handles the Firebase update
     */
    public function inlineUpdate(Request $request, $id)
    {
        try {
            $field = $request->input('field');
            $value = $request->input('value');

            // Validate field
            if (!in_array($field, ['price', 'disPrice'])) {
                return response()->json(['success' => false, 'message' => 'Invalid field. Only price and disPrice are allowed.'], 400);
            }

            // Enhanced value validation
            if (!is_numeric($value) || $value < 0) {
                return response()->json(['success' => false, 'message' => 'Invalid price value. Price must be a positive number.'], 400);
            }

            // Additional validation for maximum price (prevent extremely high values)
            if ($value > 999999) {
                return response()->json(['success' => false, 'message' => 'Price cannot exceed 999,999'], 400);
            }

            // Additional validation for discount price (if provided)
            if ($field === 'disPrice' && $value > 0) {
                // We can't validate against current price here since we don't have Firebase access
                // The frontend will handle this validation
            }

            // Return success - let frontend handle the Firebase update
            return response()->json([
                'success' => true,
                'message' => 'Validation passed. Proceeding with update.',
                'data' => [
                    'field' => $field,
                    'value' => $value
                ]
            ]);

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Food inline update validation failed', [
                'id' => $id,
                'field' => $request->input('field'),
                'value' => $request->input('value'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check your input and try again.'
            ], 400);
        }
    }


    /**
     * Download Excel template for bulk import
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'name', 'price', 'description', 'vendorID', 'categoryID',
            'disPrice', 'publish', 'nonveg', 'isAvailable', 'photo'
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        // Add sample data
        $sampleData = [
            'Sample Food Item', 10.99, 'This is a sample food description',
            'vendor_id_here', 'category_id_here', 8.99, 1, 0, 1, 'photo_url_here'
        ];

        foreach ($sampleData as $index => $value) {
            $sheet->setCellValueByColumnAndRow($index + 1, 2, $value);
        }

        // Create the Excel file
        $writer = new Xlsx($spreadsheet);
        $filename = 'food_import_template.xlsx';
        $path = storage_path('app/temp/' . $filename);

        // Ensure temp directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend();
    }

    /**
     * Import foods from Excel file
     */
    public function import(Request $request)
    {
        try {

            $request->validate([
                'file' => 'required|file|mimes:xls,xlsx|max:2048'
            ]);

            $file = $request->file('file');
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Remove header row
            $headers = array_shift($rows);

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            // Process in chunks to prevent memory issues
            $chunkSize = 50; // Process 50 rows at a time
            $chunks = array_chunk($rows, $chunkSize);

            foreach ($chunks as $chunkIndex => $chunk) {
                // Check if connection is still alive
                if (connection_aborted()) {
                    break;
                }

                foreach ($chunk as $rowIndex => $row) {
                    $actualIndex = ($chunkIndex * $chunkSize) + $rowIndex;
                try {
                    if (empty(array_filter($row))) {
                        continue; // Skip empty rows
                    }

                    $data = array_combine($headers, $row);

                    // Validate required fields
                    if (empty($data['name']) || empty($data['price'])) {
                        $errors[] = "Row " . ($actualIndex + 2) . ": Name and price are required";
                        $errorCount++;
                        continue;
                    }

                    // Prepare food data
                    $foodData = [
                        'name' => $data['name'],
                        'price' => floatval($data['price']),
                        'description' => $data['description'] ?? '',
                        'disPrice' => !empty($data['disPrice']) ? floatval($data['disPrice']) : 0,
                        'publish' => !empty($data['publish']),
                        'nonveg' => !empty($data['nonveg']),
                        'isAvailable' => !empty($data['isAvailable']),
                        'photo' => $data['photo'] ?? '',
                        'createdAt' => new \DateTime(),
                        'updatedAt' => new \DateTime()
                    ];

                    // Handle vendor ID/name
                    if (!empty($data['vendorID'])) {
                        $foodData['vendorID'] = $data['vendorID'];
                    } elseif (!empty($data['vendorName'])) {
                        // You might want to implement vendor name lookup here
                        $foodData['vendorID'] = $data['vendorName']; // Placeholder
                    }

                    // Handle category ID/name
                    if (!empty($data['categoryID'])) {
                        $foodData['categoryID'] = $data['categoryID'];
                    } elseif (!empty($data['categoryName'])) {
                        // You might want to implement category name lookup here
                        $foodData['categoryID'] = $data['categoryName']; // Placeholder
                    }

                    // Use the same REST API method to create the food item
                    $this->createFoodViaRestApi($foodData);
                    $successCount++;

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($actualIndex + 2) . ": " . $e->getMessage();
                    $errorCount++;
                }

                // Clear memory after each chunk
                if ($rowIndex === count($chunk) - 1) {
                    unset($chunk);
                    gc_collect_cycles(); // Force garbage collection
                }
                }
            }

            $message = "Import completed. Successfully imported {$successCount} items.";
            if ($errorCount > 0) {
                $message .= " {$errorCount} items failed to import.";
            }

            return redirect()->back()->with('success', $message)->with('import_errors', $errors);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Create food item via Firebase REST API
     */
    private function createFoodViaRestApi($foodData)
    {
        $projectId = env('FIREBASE_PROJECT_ID');
        $apiKey = env('FIREBASE_APIKEY');

        if (!$projectId || !$apiKey) {
            throw new \Exception('Firebase configuration not found');
        }

        // Convert data to Firebase format
        $firebaseData = [];
        foreach ($foodData as $key => $value) {
            if (is_bool($value)) {
                $firebaseData[$key] = ['booleanValue' => $value];
            } elseif (is_numeric($value)) {
                $firebaseData[$key] = ['doubleValue' => $value];
            } elseif (is_string($value)) {
                $firebaseData[$key] = ['stringValue' => $value];
            } elseif ($value instanceof \DateTime) {
                $firebaseData[$key] = ['timestampValue' => $value->format('c')];
            }
        }

        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/vendor_products?key={$apiKey}";

        $response = Http::post($url, [
            'fields' => $firebaseData
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create food item: ' . $response->body());
        }

        return $response->json();
    }
}
