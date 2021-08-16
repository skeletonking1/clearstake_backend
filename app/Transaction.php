<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
	protected $table = 'transactions';

	public function user() {
		return $this->hasOne('App\User', 'id', 'user_id');
	}
}
