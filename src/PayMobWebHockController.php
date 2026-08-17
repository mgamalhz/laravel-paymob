<?php

namespace Paymob\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PayMobWebHockController extends Controller
{
    public function run(Request $request)
    {
        $request->validate([
            'hmac' => ['required', 'string'],
        ]);

        if (! PaymobClient::checkHmac($request->all())) {
            abort(403, 'Invalid Paymob webhook signature.');
        }

        return response()->json(['message' => 'Webhook received.']);
    }

}
