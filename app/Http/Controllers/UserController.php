<?php
/**
 * File name: RestaurantController.php
 * Last modified: 2020.04.30 at 08:21:08
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 *
 */

namespace App\Http\Controllers;

use App\Services\FirebaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\VendorUsers;


class UserController extends Controller
{
    protected FirebaseStorageService $firebaseStorage;

	public function __construct(FirebaseStorageService $firebaseStorage)
    {
        $this->middleware('auth');
        $this->firebaseStorage = $firebaseStorage;
    }


    public function profile()
    {
        $user = Auth::user();

        if (!$user) {
            abort(404, 'User not found');
        }

        $id = $user->id; // or $user->vendorID if needed

        return view('users.profile', [
            'id'   => $id,
            'user' => $user,
            'placeholderImage' => asset('images/placeholder.png'),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(404, 'User not found');
        }

        // Validate minimal fields
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'phone'      => 'required|string|max:20',
            'photo'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Update basic fields
        $user->firstName   = $request->first_name;
        $user->lastName    = $request->last_name;
        $user->phoneNumber = $request->phone;

        // ----------- 🔥 IMAGE UPLOAD (Firebase Storage) -----------
        if ($request->hasFile('photo')) {
            // Delete old photo from Firebase Storage if it exists
            if ($user->profilePictureURL) {
                $this->deleteFileIfFirebase($user->profilePictureURL);
            }
            
            // Upload new photo to Firebase Storage
            $user->profilePictureURL = $this->firebaseStorage->uploadFile(
                $request->file('photo'),
                'users/profile/profile_' . time() . '_' . uniqid() . '.' . $request->file('photo')->getClientOriginalExtension()
            );
        }

        // ----------- 🔥 BANK DETAILS (JSON stored in DB) -----------
        $user->userBankDetails = json_encode([
            'bankName'     => $request->bank_name,
            'branchName'   => $request->branch_name,
            'holderName'   => $request->holder_name,
            'accountNumber'=> $request->account_number,
            'otherDetails' => $request->other_information,
        ]);

        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Profile updated successfully',
            'image'   => $user->profilePictureURL ?? null
        ]);
    }

    public function restaurant()
  {
   	  $user = Auth::user();

      if (!$user) {
          abort(404, 'User not found');
      }

      // Get firebase_id from authenticated user
      $firebaseId = Auth::id();

      // Try to find VendorUsers record with firebase_id
      $exist = VendorUsers::where('firebase_id', $firebaseId)->first();

      // Use uuid if exists, otherwise fallback to _id or user id
      $id = ($exist && isset($exist->uuid)) ? $exist->uuid : ($user->_id ?? $user->id ?? null);

      if (!$id) {
          abort(404, 'Restaurant ID not found');
      }

      return view('restaurant.myrestaurant')->with('id', $id);
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
