<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FetchStaticDataController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VocabulariesController;
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

/*Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});*/

//Fluff


Route::group(['middleware' => ['web']], function () {
    // your routes here
    Route::get('kalhmera', function () {
        //return view('welcome');
        //return phpinfo();
        return "az023...";
    });
});

Route::prefix('oauth')->group(function () {
    Route::get('callback', [AuthController::class, 'callback']);
    Route::get('validate', [AuthController::class, 'validateUser']);
    Route::get('logout', [AuthController::class, 'logoutUser']);
    Route::get('login', [AuthController::class, 'loginUser']);
});

Route::get('user/{user_id}/getInitials', [UserController::class, 'getUserInitials']);
/*
//User calls and routes
*/
//Single user, CRUD
//Route::get('user/{user_id}/data', [UserController::class, 'getUser']);
//Route::post('user/{user_id}/{user_email}/new', [UserController::class, 'insertUser']);

//Submission workflow
Route::prefix('submission')->group(function () {
    Route::post('new', [SubmissionController::class, 'newSubmission'])
        ->middleware(['checkToken:new:submission', 'attachToken']);
    Route::patch('submit', [SubmissionController::class, 'submitSubmission'])
        ->middleware(['checkToken:submit:submission', 'attachToken']);
    Route::patch('approve', [SubmissionController::class, 'approveSubmission'])
        ->middleware(['checkToken:approve:submission', 'attachToken']);
    Route::patch('reject', [SubmissionController::class, 'rejectSubmission'])
        ->middleware(['checkToken:approve:submission', 'attachToken']);
    Route::patch('edit', [SubmissionController::class, 'editSubmission'])
        ->middleware(['checkToken:view:submission', 'attachToken']);
    Route::delete('{submission_id}/delete', [SubmissionController::class, 'deleteSubmission'])
        ->middleware(['checkToken:delete:submission', 'attachToken']);
    Route::post('uploadPdf', [SubmissionController::class, 'uploadPdf'])
        ->middleware(['checkToken:upload:pdf', 'attachToken']);;
    Route::get('{submission_id}/checkSubmission', [SubmissionController::class, 'checkSubmission'])
        ->middleware(['checkToken:view:submission', 'attachToken']);;
    Route::get('{submission_id}/get', [SubmissionController::class, 'getSubmission'])
        ->middleware(['checkToken:view:submission', 'attachToken']);
});

Route::get('user/{user_id}/getSubmissions', [SubmissionController::class, 'getAllUserSubmissions'])
    ->middleware(['checkToken:view:submission', 'attachToken']);

//Admin routes
Route::prefix('admin')->group(function () {
    Route::get('getSubmissions', [SubmissionController::class, 'getAllSubmissions'])
        ->middleware(['checkToken:view-all:submission', 'attachToken']);
});

//Data Routes
Route::get('vocabularies', [VocabulariesController::class, 'fetchVocabularies']);
Route::get('fields', [VocabulariesController::class, 'fetchFields']);

//
Route::get('byPublicationCode/{publicationCode}', [\App\Http\Controllers\FetchDynamicDataController::class, 'fetchInfoByPubCode']);

Route::post('journal', [\App\Http\Controllers\DbInsertionsController::class, 'saveJournal']);

Route::get('practices/{theme}', [\App\Http\Controllers\FetchStaticDataController::class, 'fetchPracticesByTheme']);

Route::get('staticData', [\App\Http\Controllers\FetchStaticDataController::class, 'fetchStaticJson']);

Route::get('rdata', [FetchStaticDataController::class, 'getRData']);
