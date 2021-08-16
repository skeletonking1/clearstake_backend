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

use App\Setting;
use App\User;
use App\Transaction;
use App\Log;

use App\Http\Helper;

use App\Mail\ResetPasswordLink;
use App\Mail\HelpRequest;

use Laravel\Passport\Token;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class CommonController extends Controller
{
	// Send Help Request
	public function sendHelpRequest(Request $request) {
		$user = Auth::user();

		if ($user) {
			$text = $request->get('text');
			if (!$text) {
				return [
					'success' => false,
					'message' => 'Please input your question or request'
				];
			}

			Mail::to('charles@ledgerleap.com')->send(new HelpRequest($user->email, $text));
			return ['success' => true];
		}

		return ['success' => false];
	}

	// Change Email
	public function changeEmail(Request $request) {
		$user = Auth::user();

		if ($user) {
			$email = $request->get('email');

			if ($email) {
				$temp = User::where('email', $email)
										->where('id', '!=', $user->id)
										->first();

				if ($temp) {
					return [
						'success' => false,
						'message' => 'This email is already in use.'
					];
				}

				$user->email = $email;
				$user->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Change Password
	public function changePassword(Request $request) {
		$user = Auth::user();

		$password = $request->get('password');
		$new_password = $request->get('new_password');

		if ($password && $new_password && $user) {
			if (!Hash::check($password, $user->password)) {
				return [
					'success' => false,
					'message' => 'Current password is wrong'
				];
			}

			$user->password = Hash::make($new_password);
			$user->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Reset Password
	public function resetPassword(Request $request) {
		// Validator
		$validator = Validator::make($request->all(), [
			'email' => 'required|email',
			'password' => 'required',
			'token' => 'required'
		]);
		if ($validator->fails()) return ['success' => false];
		
		$email = $request->get('email');
    $password = $request->get('password');
    $token = $request->get('token');

    // Token Check
    $temp = DB::table('password_resets')
              ->where('email', $email)
              ->first();
    if (!$temp) return ['success' => false];
    if (!Hash::check($token, $temp->token)) return ['success' => false];

    // User Check
    $user = User::where('email', $email)->first();
    if (!$user) {
      return [
        'success' => false,
        'message' => 'Invalid user'
      ];
    }

    $user->password = Hash::make($password);
    $user->save();

    // Clear Tokens
    DB::table('password_resets')
        ->where('email', $email)
        ->delete();

    return ['success' => true];
	}
	
	// Send Reset Email
	public function sendResetEmail(Request $request) {
		// Validator
		$validator = Validator::make($request->all(), [
			'email' => 'required|email',
		]);
		if ($validator->fails()) return ['success' => false];

		$email = $request->get('email');
		$user = User::where('email', $email)->first();
    if (!$user) {
      return [
        'success' => false,
        'message' => 'Email is not valid'
      ];
    }

    // Clear Tokens
    DB::table('password_resets')
        ->where('email', $email)
        ->delete();
    
    // Generate New One
    $token = Str::random(60);
    DB::table('password_resets')->insert([
      'email' => $email,
      'token' => Hash::make($token),
      'created_at' => Carbon::now()
    ]);
    
    $resetUrl = $request->header('origin') . '/password/reset/' . $token . '?email=' . urlencode($email);
    
    Mail::to($user)->send(new ResetPasswordLink($resetUrl));

    return ['success' => true];
	}

	// Get Settings
	public function getSettings(Request $request) {
		$settings = Helper::getSettings();
		
		return [
			'success' => true,
			'settings' => $settings
		];
	}

	// Get Logs
	public function getLogs(Request $request) {
		$logs = [];

		// Table Variables
		$userId = (int) $request->get('userId');
		$page_id = 1;
		$page_length = 10;
		$sort_key = 'log.id';
		$sort_direction = 'desc';
		
		$data = $request->all();
		extract($data);

		$page_id = (int) $page_id;
		$page_length = (int) $page_length;
		$sort_key = trim($sort_key);
		$sort_direction = trim($sort_direction);

		if ($page_id < 1) $page_id = 1;
		$start = ($page_id - 1) * $page_length;

		$total = Log::where('user_id', $userId)
								->get()
								->count();
		$logs = Log::where('user_id', $userId)
								->orderBy($sort_key, $sort_direction)
								->offset($start)
								->limit($page_length)
								->get();
		
		return [
			'success' => true,
			'logs' => $logs,
			'total' => $total
		];
	}

	// Get Transaction List
	public function getTransactions(Request $request) {
		$user = Auth::user();

		$transactions = [];
		$total = 0;

		// Table Variables
		$page_length = 10;
		$page_id = 1;
		$sort_key = 'transactions.id';
		$sort_direction = 'desc';
		
		$data = $request->all();
		extract($data);

		$page_id = (int) $page_id;
		$page_length = (int) $page_length;
		$sort_key = trim($sort_key);
		$sort_direction = trim($sort_direction);
		
		if ($page_id < 1) $page_id = 1;
		$start = ($page_id - 1) * $page_length;

		// Role Check
		if ($user && $user->hasRole('admin')) {
			$total = Transaction::has('user')
													->get()
													->count();
			$transactions = Transaction::with('user')
																	->has('user')
																	->orderBy($sort_key, $sort_direction)
																	->offset($start)
																	->limit($page_length)
																	->get();
		} else {
			$total = Transaction::has('user')
													->where('user_id', $user->id)
													->get()
													->count();
			$transactions = Transaction::has('user')
																	->where('user_id', $user->id)
																	->orderBy($sort_key, $sort_direction)
																	->offset($start)
																	->limit($page_length)
																	->get();
		}

		return [
			'success' => true,
			'transactions' => $transactions,
			'total' => $total
		];
	}
}
