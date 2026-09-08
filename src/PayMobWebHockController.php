<?php

namespace Paymob\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Paymob\Laravel\Contracts\PaymobCapturable;
use Paymob\Laravel\Jobs\ProcessPaymobPayment;
use Paymob\Laravel\Models\Payment;

class PayMobWebHockController extends Controller
{
    public function run(Request $request)
    {
        $payload = $request->validate([
            'hmac' => ['required', 'string'],
            'obj' => ['required', 'array'],
            'obj.id' => ['required', 'integer'],
            'obj.amount_cents' => ['required', 'integer'],
            'obj.order.id' => ['required', 'integer'],
        ]);

        if (! PaymobClient::checkHmac($request->all())) {
            abort(403, 'Invalid Paymob webhook signature.');
        }

        if (! PaymobClient::recordWebhookEvent($payload, $request->all())) {
            return response()->json(['message' => 'Webhook already processed.']);
        }

        if ((bool) data_get($payload, 'obj.success') === true) {
            $payment = Payment::query()
                ->where('paymob_reference', (string) data_get($payload, 'obj.order.id'))
                ->where('status', 'processing')
                ->first();

            if ($payment !== null && $payment->order_type !== null && $payment->order_id !== null) {
                $order = $payment->order_type::query()->find($payment->order_id);

                if ($order instanceof PaymobCapturable) {
                    ProcessPaymobPayment::dispatch(
                        $order,
                        (int) data_get($payload, 'obj.id'),
                        (int) data_get($payload, 'obj.amount_cents'),
                    );
                }
            }
        }

        return response()->json(['message' => 'Webhook received.']);
    }
}
