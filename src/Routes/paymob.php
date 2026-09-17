<?php

$paymobWebhookUrl = config('paymob.paymob_webhook_url');

if (is_string($paymobWebhookUrl) && $paymobWebhookUrl !== '') {
    Route::post($paymobWebhookUrl, [\Paymob\Laravel\PayMobWebHockController::class, 'run']);
}
