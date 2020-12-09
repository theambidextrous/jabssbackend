<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\TransactionController;

/** users */
Route::prefix('/users')->group( function() {
    Route::post('/singup', [UserController::class, 'signup']);
    Route::post('/signin', [UserController::class, 'signin']);
    Route::post('/request/reset/{email}', [UserController::class, 'reqreset']);
    Route::post('/verify/{code}/reset/{email}', [UserController::class, 'verifyreset']);
    Route::post('/finish/reset', [UserController::class, 'finishreset']);
    Route::middleware('auth:api')->group( function(){
        Route::post('/resend/code', [UserController::class, 'resendcode']);
        Route::post('/verify/signup/{code}', [UserController::class, 'verifysignup']);
        Route::post('/update/profile/pic', [UserController::class, 'updatepic']);
        Route::post('/update/info', [UserController::class, 'updateinfo']);
        Route::post('/is/active', [UserController::class, 'is_active']);
        Route::post('/user/info', [UserController::class, 'userinfo']);
        Route::post('/address', [UserController::class, 'addressadd']);
        Route::post('/address/get', [UserController::class, 'addr_get']);
        Route::post('/del/address/{id}', [UserController::class, 'deladdress']);
        Route::post('/get/pref', [UserController::class, 'get_pref']);
        Route::post('/edit/pref', [UserController::class, 'edit_pref']);
    });
});
/** transactions */
Route::middleware(['auth:api', 'verified'])->group( function(){
    Route::prefix('/transactions')->group( function() {
        Route::post('/forex/meta', [TransactionController::class, 'forex_meta']);
        Route::post('/user/trxns', [TransactionController::class, 'get_trxns']);
        Route::post('/send/trxn/{id}', [TransactionController::class, 'send_trxn']);
        Route::post('/faq/all', [TransactionController::class, 'get_faq']);
        Route::post('/has/card', [TransactionController::class, 'hascard']);
        Route::post('/add/card', [TransactionController::class, 'addcard']);
        Route::post('/edit/card/{id}', [TransactionController::class, 'editcard']);
        Route::post('/validate/cc', [TransactionController::class, 'validcc']);
        Route::post('/default/cc', [TransactionController::class, 'defaultcc']);
        Route::post('/delete/card/{id}', [TransactionController::class, 'delcard']);
        Route::post('/get/cards', [TransactionController::class, 'getcards']);
        Route::post('/get/card/{id}', [TransactionController::class, 'getcard']);

        Route::post('/init/send', [TransactionController::class, 'send']);
    });
});
/** fallback */
Route::fallback(function () {
    return response()->json(['status' => 404,'softbct_error' => 'Not Found!'], 404);
});
Route::get('/', function (Request $request) {
    return response(['status' => 499, 'message' => 'point of no return']);
});
Route::fallback(function () {
    return response(['status'=> 499, 'message' => 'oops! Congrats! you\'ve reached point of no return']);
});