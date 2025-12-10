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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

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
        $settings = DB::table('settings')
            ->whereIn('document_name', ['AdminCommission', 'placeholderImage'])
            ->get()
            ->keyBy('document_name')
            ->map(function ($setting) {
                $setting->fields = json_decode($setting->fields ?? '[]', true) ?? [];

                return $setting;
            });
        $vendor = $this->findVendorForUser($user);
        $story = $vendor
            ? Story::where('vendor_id', $vendor->id)->latest('created_at')->first()
            : null;

        return view('restaurant.myrestaurant', [
            'user' => $user,
            'vendor' => $vendor,
            'zones' => Zone::active()->orderBy('name')->get(),
            'cuisines' => VendorCuisine::active()->orderBy('title')->get(),
            'categories' => VendorCategory::active()->orderBy('title')->get(),
            'story' => $story,
            'settings' => $settings,
            'currency' => $this->currencyMeta(),
            'filterOptions' => $this->filterOptions(),
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
                'cuisineID' => $request->input('cuisine_id'),
            ];

            // 👇 Auto-generate createdAt only once
            if (blank($vendor->createdAt)) {
                $payload['createdAt'] = now('Asia/Kolkata')->format('M j, Y g:i A');
            }

            // Category and cuisine titles...
            if ($request->filled('category_ids')) {
                $payload['categoryTitle'] = VendorCategory::whereIn('id', $request->category_ids)
                    ->pluck('title')
                    ->filter()
                    ->values()
                    ->all();
            }

            if ($request->filled('cuisine_id')) {
                $payload['cuisineTitle'] = optional(VendorCuisine::find($request->cuisine_id))->title;
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

                $this->deleteFileIfLocal($vendor->photo ?? null);
                $payload['photo'] = null;

            } elseif ($request->hasFile('photo')) {

                // delete old
                $this->deleteFileIfLocal($vendor->photo ?? null);

                // save new to Firebase Storage
                $payload['photo'] = $this->firebaseStorage->uploadFile(
                    $request->file('photo'),
                    'restaurants/photo_' . time() . '.' . $request->file('photo')->getClientOriginalExtension()
                );
            }

            // ------------------ Gallery ------------------
            $gallery = $vendor->photos ?? [];
            $removeGallery = $request->input('remove_gallery', []);

            if (!empty($removeGallery)) {
                foreach ($removeGallery as $photo) {
                    $this->deleteFileIfLocal($photo);
                }

                $gallery = array_values(array_filter($gallery, fn ($p) => !in_array($p, $removeGallery)));
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $file) {
                    $gallery[] = $this->firebaseStorage->uploadFile(
                        $file,
                        'restaurants/gallery/gallery_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension()
                    );
                }
            }

            $payload['photos'] = $gallery;

            // Filters, working hours, special discounts...
            $vendor->fill($payload);
            $vendor->save();

            $this->storeStoryMedia($vendor, $request);
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

        $currency = Currency::where('isActive', true)->first();

        return $this->currencyMeta = [
            'symbol' => $currency->symbol ?? '₹',
            'symbol_at_right' => (bool) ($currency->symbolAtRight ?? false),
            'decimal_digits' => $currency->decimal_degits ?? 2,
        ];
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
}

