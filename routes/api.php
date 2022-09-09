<?php
  
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
  
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\LoanApplicationsApiController;
use App\Http\Controllers\API\PassportAuthController;
  
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
  
  
Route::controller(RegisterController::class)->group(function(){
    Route::post('register', 'register');
    Route::post('login', 'login');
});
        
Route::middleware('auth:api')->group( function () {
    Route::get('get-user', [PassportAuthController::class, 'userInfo']);
    Route::post('loan-applications/store', 'App\Http\Controllers\API\LoanApplicationsApiController@store');
    Route::post('loan-applications/show', 'App\Http\Controllers\API\LoanApplicationsApiController@show');
    Route::post('loan-applications/update', 'App\Http\Controllers\API\LoanApplicationsApiController@update');
    Route::post('loan-applications/destroy', 'App\Http\Controllers\API\LoanApplicationsApiController@destroy');
    Route::post('loan-applications/emiTransaction', 'App\Http\Controllers\API\LoanApplicationsApiController@emiTransaction');
    Route::post('loan-applications/getEmiTransaction', 'App\Http\Controllers\API\LoanApplicationsApiController@getEmiTransaction');
    Route::post('loan-applications/send', 'App\Http\Controllers\API\LoanApplicationsApiController@send');
    Route::post('loan-applications/showAnalyze', 'App\Http\Controllers\API\LoanApplicationsApiController@showAnalyze');
});