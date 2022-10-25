<?php

use App\ApanioFBApi\FBApiProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Models\User;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::resource('products', ProductController::class)->only(
    [
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ]);

Route::post('login-fb', function (Request $request) {
    if (!$request->get('fb_access_token'))
        return;

    if (!FBApiProcessor::checkAccessToken($request->get('fb_access_token'), $request->get('fb_user_id')))
        return;
    
    $catalogID = FBApiProcessor::queryCatalogID($request->get('fb_access_token'));

    $user = User::find(1);

    $user->fb_access_token = $request->get('fb_access_token');  // hay que verificar que esta data sea cierta
    $user->fb_catalog_id = $catalogID;  // hay que verificar que esta data sea cierta
    $user->save();
});
