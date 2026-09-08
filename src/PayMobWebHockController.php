<?php

namespace Paymob\Laravel;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Paymob\Laravel\DTO\PaymobWebhookPayload;
use Paymob\Laravel\Events\PaymobWebhookReceived;

class PayMobWebHockController extends Controller
{
    public function __invoke(Request $request)
    {
        return $this->run($request);
    }

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
