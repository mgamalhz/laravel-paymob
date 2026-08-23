<?php

namespace Paymob\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PayMobWebHockController extends Controller
{
    public function run(Request $request)
    {
        $payload = $request->validate([
            'hmac' => ['required', 'string'],
            'obj' => ['required', 'array'],
            'obj.id' => ['required', 'integer'],
        ]);

        if (! PaymobClient::checkHmac($request->all())) {
            abort(403, 'Invalid Paymob webhook signature.');
        }

        if (! PaymobClient::recordWebhookEvent($payload, $request->all())) {
            return response()->json(['message' => 'Webhook already processed.']);
        }

        return response()->json(['message' => 'Webhook received.']);
    }
}
