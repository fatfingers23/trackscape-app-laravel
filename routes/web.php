<?php


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

use App\Http\Controllers\ClanController;
use App\Http\Controllers\DropLogController;
use App\Http\Controllers\DropLogsController;
use App\Http\Controllers\PbController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/faq', function () {
    return view('faq');
})->name('faq');


Route::prefix('clan')->middleware('auth:sanctum')->group(function () {
    Route::post('signup', [ClanController::class, "signUpClan_post"]);
});


Route::get('/invite/{rsn}', [\App\Http\Controllers\Invite::class, 'invite']);
Route::get('/invite', function () {
    return redirect("https://discord.gg/PWy8pm782p");

});

Route::get('/collectionlog/{clanId}', [\App\Http\Controllers\CollectionLogsController::class, 'index'])->name('collection-logs');
Route::get('/pb/{clanId}', [PbController::class, 'index'])->name('pb');


Route::name('clan')->prefix('clan')->group(function () {
    Route::get('/search', [ClanController::class, 'clanSearch'])->name('search');
    Route::get('/{id}/members', [ClanController::class, 'memberList'])->name('members');
    Route::get('/{clanName}/{new?}', [ClanController::class, "landingPage"])->name('landing-page');
});

Route::get('/droplogs/{confirmationCode}/{startDate}/{endDate}', [DropLogController::class, 'csvOfDropLog'])->name('drop-logs');