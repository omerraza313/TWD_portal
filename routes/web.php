<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WordpressController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TestController;
use Doctrine\Inflector\WordInflector;
use Illuminate\Support\Facades\DB;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // return view('welcome');
    $count = DB::table('products')->where('process_status', 1)->count();
    echo $count;
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::group(['middleware'=>['auth']], function(){
    Route::get('/AssignRole', [UserController::class, 'AssignRole'])->name('user.assignrole');

    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('admin.dash')->middleware('can:view dashboard');

    Route::get('/product', [ProductController::class, 'index'])->name('product.view');

    Route::get('/attributes', [AttributeController::class, 'index'])->name('attributes.view');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.view');

    Route::get('sync', [SyncController::class, 'index'])->name('synch.view');

    Route::get('/resend-to-shopify', [ShopifyController::class, 'updateToShopify'])->middleware('can:resend to shopify')->name('shopify.update');
    Route::get('/categories-to-shopify', [ShopifyController::class, 'pushCategoriesToShopify'])->middleware('can:resend to shopify')->name('shopify.collection');
    //GRAPHQLI Routes
    Route::get('/payload-to-shopify', [TestController::class, 'sendProductGraphiQL']);
    Route::get('/payload-to-shopify2', [TestController::class, 'bulkCreateProducts']);

    //GRAPHQLI Ends
    Route::get('/fetch-from-wordpress', [WordPressController::class, 'fetchFromWordpress'])->middleware('can:fetch from wordpress')->name('wordpress.fetch');
    Route::get('/update-price-wordpress', [WordPressController::class, 'fetchPricesFromWordpress'])->middleware('can:fetch from wordpress')->name('wordpress.updatePrices');

});

