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
use App\Transaction;

use App\Mail\SubInvitation;

use Laravel\Passport\Token;
use Carbon\Carbon;

class AdminController extends Controller
{
	// Reset User Password
	public function resetUserPassword(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$userId = (int) $request->get('userId');
			$password = $request->get('password');

			if ($userId && $password) {
				$user = User::where('role', 'user')->where('id', $userId)->first();
				if (!$user) {
					return [
						'success' => false,
						'message' => 'Invalid user'
					];
				}

				$user->password = Hash::make($password);
				$user->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Withdraw
	public function withdraw(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$userId = (int) $request->get('userId');
			$amount = (int) $request->get('amount');

			if ($userId && $amount > 0) {
				$user = User::where('role', 'user')->where('id', $userId)->first();
				if ($user && (int) $user->balance >= $amount) {
					$user->balance = (int) $user->balance - $amount;
					if (!$user->withdraw_sum) $user->withdraw_sum = 0;
					$user->withdraw_sum = (int) $user->withdraw_sum + $amount;
					$user->save();

					$user->last_withdraw_date = $user->updated_at;
					$user->save();

					Helper::subtractBalance($amount);
					Helper::updateSetting('last_withdraw_date', Carbon::now());

					Helper::addTransaction([
		        'user_id' => $user->id,
		        'amount' => -$amount,
		        'action' => 'Withdraw Processed',
		        'balance' => $user->balance,
		      ]);
					return ['success' => true];
				}
			}
		}

		return ['success' => false];
	}

	// Update Total Balance
	public function updateBalance(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$balance = (int) $request->get('balance');

			if ($balance > 0) {
				$settings = Helper::getSettings();
				
				$current_balance = 0;
				if ($settings && isset($settings['total_balance']))
					$current_balance = (int) $settings['total_balance'];
				
				if ($current_balance <= 0) {
					return [
						'success' => false,
						'message' => 'The current balance is zero'
					];
				}

				$rate = (float) ($balance / $current_balance);

				Helper::updateBalance($balance);
				Helper::updateUsersBalance($rate);
				Helper::updateSetting('last_inflation_date', Carbon::now());

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Get Values
	public function getValues(Request $request) {
		$user = Auth::user();
		
		$last_staking_date = "";
		if ($user && $user->hasRole('admin')) {
			$transaction = Transaction::where('action', 'Inflation Deposit')
																->orderBy('created_at', 'desc')
																->first();
			if ($transaction) $last_staking_date = $transaction->created_at;
		}

		return [
			'success' => true,
			'last_staking_date' => $last_staking_date
		];
	}

	// Get Single User
	public function getSingleUser($userId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$user = User::where('role', 'user')->where('id', $userId)->first();
			if ($user) {
				$total = 0;
				$transactions = [];

				// Table Variables
				$sort_key = 'transactions.id';
				$sort_direction = 'desc';
				
				$data = $request->all();
				extract($data);

				$sort_key = trim($sort_key);
				$sort_direction = trim($sort_direction);
				
				$total = Transaction::has('user')
													->where('user_id', $user->id)
													->get()
													->count();
				$transactions = Transaction::has('user')
																	->where('user_id', $user->id)
																	->orderBy($sort_key, $sort_direction)
																	->get();

				return [
					'success' => true,
					'total' => $total,
					'transactions' => $transactions,
					'user' => $user
				];
			}
		}

		return ['success' => false];
	}

	// Get All Users
	public function getAllUsers(Request $request) {
		$user = Auth::user();
		$users = [];

		if ($user && $user->hasRole('admin')) {
			$users = User::where('role', 'user')->orderBy('first_name', 'asc')->get();
		}

		return [
			'success' => true,
			'users' => $users
		];
	}

	// Get User List
	public function getUsers(Request $request) {
		$user = Auth::user();

		$users = [];
		$total = 0;
		
		// Table Variables
		$page_length = 10;
		$sort_key = 'users.first_name';
		$sort_direction = 'asc';
		$page_id = 1;
		
		$data = $request->all();
		extract($data);

		$page_id = (int) $page_id;
		$page_length = (int) $page_length;
		$sort_key = trim($sort_key);
		$sort_direction = trim($sort_direction);
		
		if ($page_id < 1) $page_id = 1;
		$start = ($page_id - 1) * $page_length;

		// Role Check
		if ($user && $user->hasRole('admin')) { // Admin
			$total = User::where('id', '>', 0)
										->where('role', '!=', 'admin')
										->get()
										->count();
			$users = User::where('id', '>', 0)
										->where('role', '!=', 'admin')
										->orderBy($sort_key, $sort_direction)
										->offset($start)
										->limit($page_length)
										->get();
		}

		return [
			'success' => true,
			'total' => $total,
			'users' => $users,
		];
	}

	// deposit
	public function deposit(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$userId = (int) $request->get('userId');
			$amount = (int) $request->get('amount');

			if ($userId && $amount > 0) {
				$user = User::where('role', 'user')->where('id', $userId)->first();
				if ($user) {
					$user->balance = (int) $user->balance + $amount;
					$user->save();

					Helper::addBalance($amount);

					Helper::addTransaction([
						'user_id' => $user->id,
						'amount' => $amount,
						'action' => 'Deposit',
						'balance' => $user->balance,
		      		]);
					return ['success' => true];
				}
			}
		}

		return ['success' => false];
	}
}
