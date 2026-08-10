<?php


Route::post(config('paymob.paymob_webhook_url'), [\Paymob\Laravel\PayMobWebHockController::class, 'run']);
