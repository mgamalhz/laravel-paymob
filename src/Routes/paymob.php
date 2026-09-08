<?php
use Illuminate\Support\Facades\Route;
use Paymob\Laravel\PayMobWebHockController;


Route::post(config('paymob.paymob_webhook_url'), [\Paymob\Laravel\PayMobWebHockController::class, 'run']);
