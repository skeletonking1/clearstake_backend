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

use App\User;

use Laravel\Passport\Token;
use Carbon\Carbon;

class InstallController extends Controller
{
	public function install() {
		// Setting Roles
		$role = Role::where(['name' => 'admin'])->first();
		if (!$role) Role::create(['name' => 'admin']);

		$role = Role::where(['name' => 'user'])->first();
		if (!$role) Role::create(['name' => 'user']);

		echo "Roles created!<br/>";

		// Create Admin
		$email = 'ledgerleapllc@gmail.com';
		$user = User::where('email', $email)->first();
		if (!$user) {
			$user = new User;
			$user->first_name = 'Ledger';
			$user->last_name = 'Leap';
			$user->email = $email;
			$user->email_verified = 1;
			$user->password = Hash::make("ledgerleapllc");
			$user->role = "admin";
			$user->inflation = 10;
			$user->balance = 0;
			$user->save();
		}

		if (!$user->hasRole('admin'))
			$user->assignRole('admin');
		echo "Admin Created!<br/>";
	}
}
