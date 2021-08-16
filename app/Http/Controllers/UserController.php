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
use App\TokenPrice;

use App\Mail\RequestWithdraw;

use Carbon\Carbon;

class UserController extends Controller
{
	// Get GraphInfo
	public function getGraphInfo(Request $request) {
		$user = Auth::user();
		$graphData = [];

		if ($user && $user->hasRole('user')) {
			$items = TokenPrice::orderBy('created_at', 'asc')->get();
			if ($items && count($items)) {
				foreach ($items as $item) {
					$name = Carbon::parse($item->created_at)->format("Y-m-d H:i");
					$graphData[] = [
						'name' => $name,
						'Price' => $item->price
					];
				}
			}
		}

		return [
			'success' => true,
			'graphData' => $graphData,
		];
	}

	// Withdraw
	public function withdraw(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('user')) {
			$amount = (int) $request->get('amount');
			if ($amount > 0 && (int) $user->balance >= $amount) {
				Mail::to('charles@ledgerleap.com')->send(new RequestWithdraw($user->first_name . ' ' . $user->last_name, $amount));
				
				$user->last_withdraw_request = Carbon::now();
				$user->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}
}
