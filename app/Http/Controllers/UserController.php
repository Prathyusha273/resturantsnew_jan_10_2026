<?php
/**
 * File name: RestaurantController.php
 * Last modified: 2020.04.30 at 08:21:08
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 *
 */

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\VendorUsers;


class UserController extends Controller
{

	public function __construct()

    {
        $this->middleware('auth');
    }


	public function profile()
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
            abort(404, 'User profile ID not found');
        }

      	return view('users.profile')->with('id', $id);
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

}
