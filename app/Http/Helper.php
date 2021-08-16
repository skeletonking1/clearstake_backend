<?php

namespace App\Http;

use Illuminate\Support\Facades\Http;

use App\User;
use App\Setting;
use App\Transaction;
use App\TokenPrice;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

class Helper {
	// Get Token Price
	public static function getTokenPrice() {
		$url = 'https://pro-api.coinmarketcap.com/v1/tools/price-conversion';

		$response = Http::withHeaders([
			'X-CMC_PRO_API_KEY' => env('COINMARKETCAP_KEY')
		])->get($url, [
			'amount' => 1,
			'symbol' => 'CSPR',
			'convert' => 'USD'
		]);

		return $response->json();
	}

	// Get Settings
	public static function getSettings() {
		$settings = [];

		// Get Settings
		$items = Setting::where('id', '>', 0)->get();
		if ($items) {
			foreach ($items as $item) {
				$settings[$item->s_key] = $item->s_value;
			}
		}

		// Get Token Price
		$tokenPrice = TokenPrice::orderBy('created_at', 'desc')->first();
		if ($tokenPrice)
			$settings['token_price'] = (float) $tokenPrice->price;
		else
			$settings['token_price'] = 0;
		return $settings;
	}

	// Update Setting
	public static function updateSetting($key, $value) {
		$setting = Setting::where('s_key', $key)->first();
		if (!$setting) $setting = new Setting;
		$setting->s_key = $key;
		$setting->s_value = $value;
		$setting->save();
	}

	// Add Transaction
	public static function addTransaction($data) {
		$transaction = new Transaction;
		$transaction->user_id = $data['user_id'];
		$transaction->amount = (int) $data['amount'];
		$transaction->action = $data['action'];
		$transaction->balance = $data['balance'];
		$transaction->save();
	}

	// Add Balance
	public static function addBalance($balance) {
		$settings = self::getSettings();
		$total = isset($settings['total_balance']) ? (int) $settings['total_balance'] : 0;
		$total += (int) $balance;

		self::updateSetting('total_balance', $total);
	}

	// Subtract Balance
	public static function subtractBalance($balance) {
		$settings = self::getSettings();
		$total = isset($settings['total_balance']) ? (int) $settings['total_balance'] : 0;
		$total -= (int) $balance;
		
		self::updateSetting('total_balance', $total);
	}

	// Update Balance
	public static function updateBalance($balance) {
		self::updateSetting('total_balance', $balance);
	}

	// Update Users Balance
	public static function updateUsersBalance($rate) {
		$users = User::where('role', 'user')->where('id', '>', 0)->get();
		if ($users) {
			foreach ($users as $user) {
				$obalance = $balance = 0;
				if (isset($user->balance)) {
					$obalance = $balance = (int) $user->balance;
				}

				$balance = $balance * (float) $rate;
				$balance = ceil($balance);

				$diff = $balance - $obalance;

				$user->balance = $balance;
				$user->save();

				$user->last_inflation_date = $user->updated_at;
				$user->save();

				if ($diff != 0) {
					self::addTransaction([
		        'user_id' => $user->id,
		        'amount' => $diff,
		        'action' => 'Inflation Deposit',
		        'balance' => $balance
		      ]);
		    }
			}
		}
	}

	// Generate Random String
	public static function generateRandomString($length_of_string) {
    // String of all alphanumeric character
    $str_result = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ'; 
  	
    // Shufle the $str_result and returns substring
    return substr(str_shuffle($str_result), 0, $length_of_string); 
	}

	// Generate Random Two FA Code
	public static function generateTwoFACode() {
		$randlist = ['1','2','3','4','5','6','7','8','9','A','C','E','F','G','H','K','N','P','Q','R','T','W','X','Z'];
		$code1 = $randlist[rand(0,23)];
		$code2 = $randlist[rand(0,23)];
		$code3 = $randlist[rand(0,23)];
		$code4 = $randlist[rand(0,23)];
		$code5 = $randlist[rand(0,23)];
		$code6 = $randlist[rand(0,23)];
		$code = $code1 . $code2 . $code3 . $code4 . $code5 . $code6;
		return $code;
	}

	// Generate GUID
	public static function generateGUID() {
		$byte1 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte2 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte3 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte4 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte5 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte6 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte7 = "12";
		$byte8 = "d3";
		$byte9 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte10 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte11 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte12 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte13 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte14 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte15 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
		$byte16 = str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);

		$guid = $byte1.$byte2.$byte3.$byte4.'-'.$byte5.$byte6.'-'.$byte7.$byte8.'-'.$byte9.$byte10.'-'.$byte11.$byte12.$byte13.$byte14.$byte15.$byte16;

		return $guid;
	}

	// Custom Encode
	public static function b_encode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	// Custom Decode
	public static function b_decode($data) {
		return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
	}

	// Strip Symbol
	public static function stripSymbol($string) {
		$string = str_replace(' ', '', $string);
		$string = str_replace('-', '', $string);
		$string = str_replace('(', '', $string);
		$string = str_replace(')', '', $string);

		return $string;
	}

	public static function isLocal() {
		$whitelist = [
			'127.0.0.1',
			'::1'
		];

		if(in_array($_SERVER['REMOTE_ADDR'], $whitelist)) return true;
		return false;
	}
}
?>
