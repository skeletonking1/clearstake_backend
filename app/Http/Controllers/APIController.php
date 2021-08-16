<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

use App\Http\Helper;

use App\User;
use App\Log;

use App\Mail\SubInvitation;

use Laravel\Passport\Token;
use Carbon\Carbon;

class APIController extends Controller
{
  // Get Auth User
  public function getMe(Request $request) {
    $user = Auth::user();
    if ($user) {
      $user = User::where('id', $user->id)->first();

      return [
        'success' => true,
        'me' => $user
      ];
    }

    return ['success' => false];
  }

  // User Login
  public function login(Request $request) {
  	// Validator
    $validator = Validator::make($request->all(), [
      'email' => 'required',
      'password' => 'required'
    ]);
    if ($validator->fails()) {
      return [
        'success' => false,
        'message' => 'Login info is not correct'
      ];
    }

    $email = $request->get('email');
    $password = $request->get('password');
    $ip = $request->get('ip');

    $user = User::where('email', $email)->first();

    if (!$user) {
      return [
        'success' => false,
        'message' => 'Email does not exist'
      ];
    }

    if (!Hash::check($password, $user->password)) {
      return [
        'success' => false,
        'message' => 'Password is not correct'
      ];
    }

    if (!$user->email_verified) {
      return [
        'success' => false,
        'message' => 'Email is not verified'
      ];
    }

    // Log Table
    $log = new Log;
    $log->user_id = $user->id;
    // $log->ip = $request->ip();
    $log->ip = $ip ?? "";
    $log->save();

    Token::where([
      'user_id' => $user->id,
      'name' => 'API Access Token'
    ])->delete();
    $tokenResult = $user->createToken('API Access Token');

    $user->accessTokenAPI = $tokenResult->accessToken;
    
    return [
      'success' => true,
      'user' => $user
    ];
  }

  // Invite User
  public function inviteUser(Request $request) {
    $user = Auth::user();
    if (!$user || !$user->hasRole('admin'))
      return ['success' => false];

    // Validator
    $validator = Validator::make($request->all(), [
      'first_name' => 'required',
      'last_name' => 'required',
      'email' => 'required',
      'balance' => 'required',
    ]);
    if ($validator->fails()) {
      return [
        'success' => false,
        'message' => 'Provide all the necessary information'
      ];
    }

    $first_name = $request->get('first_name');
    $last_name = $request->get('last_name');
    $email = $request->get('email');
    $balance = (int) $request->get('balance');

    if ($first_name && $last_name && $email && $balance > 0) {
      // User Check
      $tempUser = User::where('email', $email)->first();
      if ($tempUser) {
        return [
          'success' => false,
          'message' => 'The email is already in use'
        ];
      }

      $code = Str::random(6);

      $user = new User;
      $user->first_name = $first_name;
      $user->last_name = $last_name;
      $user->email = $email;
      $user->role = 'user';
      $user->password = '';
      $user->balance = $balance;
      $user->confirmation_code = $code;
      $user->save();
      $user->assignRole('user');

      Helper::addBalance((int) $user->balance);
      Helper::addTransaction([
        'user_id' => $user->id,
        'amount' => $balance,
        'action' => 'Initial Balance',
        'balance' => $user->balance,
      ]);
      
      $link = $request->header('origin') . '/invitation/' . Helper::b_encode($user->id . '::' . $email . '::' . $code);

      $title = "Casper Portal Invitation";
      Mail::to($user)->send(new SubInvitation($link, $first_name, $title));

      return ['success' => true];
    }

    return ['success' => false];
  }

  // Get Invitation Data from Code
  public function getInvitationData($code, Request $request) {
    $string = Helper::b_decode($code);
    if ($string) {
      $explode = explode('::', $string);

      $id = isset($explode[0]) ? (int)$explode[0] : null;
      $email = isset($explode[1]) ? $explode[1] : null;
      $code = isset($explode[2]) ? trim($explode[2]) : null;

      if ($id && $email && $code) {
        $user = User::find($id);

        if (
          $user && 
          $user->email == $email && 
          $user->confirmation_code == $code
        ) {
          if (!$user->password) {
            return [
              'success' => true,
              'user' => $user,
              'verified' => false,
            ];
          } else {
            return [
              'success' => true,
              'user' => $user,
              'verified' => true,
            ];
          }
        }
      }
    }

    return ['success' => false];
  }

  // Finish Invitation
  public function finishInvitation(Request $request) {
    $userId = (int) $request->get('userId');
    $code = $request->get('code');
    $password = $request->get('password');

    if ($userId && $code && $password) {
      $string = Helper::b_decode($code);
      if ($string) {
        $explode = explode('::', $string);

        $id = isset($explode[0]) ? (int)$explode[0] : null;
        $email = isset($explode[1]) ? $explode[1] : null;
        $code = isset($explode[2]) ? trim($explode[2]) : null;

        if ($id && $email && $code && $userId == $id) {
          $user = User::where('id', $userId)->first();

          if (!$user) {
            return [
              'success' => false,
              'message' => 'Invalid user'
            ];
          }

          if ($user->password || $user->email != $email) {
            return [
              'success' => false,
              'message' => 'Invalid user'
            ];
          }

          $user->password = Hash::make($password);
          $user->email_verified = true;
          $user->email_verified_at = Date::now();
          $user->save();

          Token::where([
            'user_id' => $user->id,
            'name' => 'API Access Token'
          ])->delete();
          $tokenResult = $user->createToken('API Access Token');
          
          $user->accessTokenAPI = $tokenResult->accessToken;

          return [
            'success' => true,
            'user' => $user
          ];
        } 
      }
    }

    return ['success' => false];
  }
}
