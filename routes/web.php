<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
Route::get('/login', \Auth0\Laravel\Http\Controller\Stateful\Login::class)->name('login');
Route::get('/logout', \Auth0\Laravel\Http\Controller\Stateful\Logout::class)->name('logout');
Route::get('/auth0/callback', \Auth0\Laravel\Http\Controller\Stateful\Callback::class)->name('auth0.callback');


Route::get('/', function () {
    if (Auth::check()) {
        return view('auth0.user');
    }

    return view('auth0/guest');
})->middleware(['auth0.authenticate.optional']);


Route::get('/required', function () {

    return view('auth0.user');
})->middleware(['auth0.authenticate']);
*/

//Route::get('/api/public', function () {
//    return response()->json([
//        'message' => 'Hello from a public endpoint! You don\'t need to be authenticated to see this.',
//        'authorized' => Auth::check(),
//        'user' => Auth::check() ? json_decode(json_encode((array) Auth::user(), JSON_THROW_ON_ERROR), true) : null,
//    ], 200, [], JSON_PRETTY_PRINT);
//})->middleware(['auth0.authorize.optional']);


//Route::get('/api/private', function () {
//    return response()->json([
//        'message' => 'Hello from a private endpoint! You need to be authenticated to see this.',
//        'authorized' => Auth::check(),
//        'user' => Auth::check() ? json_decode(json_encode((array) Auth::user(), JSON_THROW_ON_ERROR), true) : null,
//    ], 200, [], JSON_PRETTY_PRINT);
//})->middleware(['auth0.authorize']);


