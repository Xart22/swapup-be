<?php

use App\Http\Controllers\API\AusPostController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ItemsController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\VariantsController;
use App\Http\Controllers\API\SwapUpKitController;
use App\Http\Controllers\API\RequestCashoutController;
use App\Http\Controllers\API\ShopifyRequestCard;
use App\Http\Controllers\API\StripeController;
use App\Http\Controllers\WebHookConsignController;


Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('register-with-admin', 'registerWithAdmin');
    Route::post('login', 'login');
    Route::post(base64_encode("/sendMailerPassForgot"), [AuthController::class, 'directLinkMailer']);
    Route::patch(base64_encode("/ForgotPass"), [AuthController::class, 'updatePassword']);
    Route::post('/email/resend', [AuthController::class, 'resendMailerVerify'])->name('verification.resend');
    Route::get('/email/verify', [AuthController::class, 'verifyAtMailer'])->name('verification.verify');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'getUser']);
    Route::post('/cashout', [AuthController::class, 'cashout']);
    Route::put('/update/profile', [AuthController::class, 'updateProfile']);
    Route::get('/swap-up-kit', [SwapUpKitController::class, 'getSwapUpKit']);
    Route::get('/variant/{id}', [VariantsController::class, 'showVariant']);

    // Items Section
    Route::get('/items', [ItemsController::class, 'listItems']);
    Route::get('/variants', [VariantsController::class, 'showVariants']);


    Route::post("/createGift", [ShopifyRequestCard::class, 'requestCreateGiftCard']);

    Route::post('/create-payment-intent', [StripeController::class, 'createPaymentIntent']);
    Route::post('/retrieve-payment', [StripeController::class, 'retrievePayment']);
    Route::post('/pay-with-credit', [OrderController::class, 'payWithCredit']);

    Route::post('/check-suburb', [AusPostController::class, 'validateSuburb']);
    Route::get('/create-label/{order_id}', [AusPostController::class, 'createLabel']);

    Route::get('/orders', [OrderController::class, 'getOrderByUser']);
    Route::get("/recent_activity", [AuthController::class, 'recentActivity']);

    Route::post(base64_encode("/sendMailer"), [AuthController::class, 'checkEditMailer']);
    Route::post(base64_encode("/generateOTPMailerSend"), [AuthController::class, 'generateOTPMailerSend']);
    Route::patch(base64_encode("/verify-otp"), [AuthController::class, 'validateOTP']);
});

Route::middleware('auth:sanctum', 'admin')->group(function () {
    Route::prefix('variants')->group(function () {
        Route::post('/add', [VariantsController::class, 'addVariant']);
        Route::put('/update', [VariantsController::class, 'updateVariant']);
        Route::delete('/delete', [VariantsController::class, 'deleteVariant']);
        Route::get('/list', [VariantsController::class, 'listVariants']);
    });

    Route::post('/swap-up-kit', [SwapUpKitController::class, 'upsert']);

    Route::get('/list_card', [ShopifyRequestCard::class, 'getListCard']);
    Route::get('/search_card', [ShopifyRequestCard::class, 'searchForListGiftCard']);
    Route::get('/export_gift_card', [ShopifyRequestCard::class, 'exportForListGiftCard']);
    Route::get("/export_cashout", [RequestCashoutController::class, 'exportcashout']);

    Route::prefix('cashout')->group(function () {
        Route::post('/update', [RequestCashoutController::class, 'updateCashout']);
        Route::get('/list', [RequestCashoutController::class, 'listCashout']);
    });

    Route::post('/get-all-order', [OrderController::class, 'getOrderByDate']);
    Route::get('/get-all-order', [OrderController::class, 'getAllOrder']);
    Route::put('/update-order-status', [OrderController::class, 'updateOrderStatus']);
    Route::post('/add-order', [OrderController::class, 'addNewOrderManual']);


    Route::get('/manualitem', [ItemsController::class, 'getManualItems']);
    Route::delete('/resetitem', [ItemsController::class, 'resetItems']);
    Route::get('/getitembyid', [ItemsController::class, 'getByIdItems']);

    Route::get('/users/search', [AuthController::class, 'searchUsers']);
});

Route::post(base64_encode('/live-data'), [WebHookConsignController::class, 'liveTimeData']);
