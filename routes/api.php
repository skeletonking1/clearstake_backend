<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/invitation/{code}', 'APIController@getInvitationData');

Route::put('/invitation', 'APIController@finishInvitation');

Route::post('/login', 'APIController@login');
Route::post('/common/send-reset-email', 'CommonController@sendResetEmail');
Route::post('/common/reset-password', 'CommonController@resetPassword');

Route::group(['middleware' => ['auth:api']], function () {
	// GET
	Route::get('/me', 'APIController@getMe');
	
	// POST
	Route::post('/user', 'APIController@inviteUser');
});

Route::group(['prefix' => 'user', 'middleware' => ['auth:api']], function() {
	// PUT
	Route::put('/withdraw', 'UserController@withdraw');
	
	// GET
	Route::get('/graph-info', 'UserController@getGraphInfo');
});

Route::group(['prefix' => 'admin', 'middleware' => ['auth:api']], function() {
	// GET
	Route::get('/users', 'AdminController@getUsers');
	Route::get('/users/all', 'AdminController@getAllUsers');
	Route::get('/user/{userId}', 'AdminController@getSingleUser');
	Route::get('/values', 'AdminController@getValues');
	
	// PUT
	Route::put('/balance', 'AdminController@updateBalance');
	Route::put('/withdraw', 'AdminController@withdraw');
	Route::put('/deposit', 'AdminController@deposit');

	// POST
	Route::post('/reset-user-password', 'AdminController@resetUserPassword');
});

Route::group(['prefix' => 'common', 'middleware' => ['auth:api']], function() {
	// GET
	Route::get('/settings', 'CommonController@getSettings');
	Route::get('/transactions', 'CommonController@getTransactions');
	Route::get('/logs', 'CommonController@getLogs');
	
	// POST
	Route::post('/send-help-request', 'CommonController@sendHelpRequest');
	Route::post('/change-email', 'CommonController@changeEmail');
	Route::post('/change-password', 'CommonController@changePassword');
});
