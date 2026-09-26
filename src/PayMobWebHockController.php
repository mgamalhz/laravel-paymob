<?php

namespace Paymob\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Paymob\Laravel\Contracts\PaymobCapturable;
use Paymob\Laravel\Jobs\ProcessPaymobPayment;
use Paymob\Laravel\Models\Payment;
use Paymob\Laravel\Support\PaymobLogEvents;
use Paymob\Laravel\Support\PaymobLogger;

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
            "obj.success" => ['required', 'boolean'],
        ]);

        PaymobLogger::info(PaymobLogEvents::WEBHOOK_RECEIVED, $this->logContext($request, $payload));

        if (! PaymobClient::checkHmac($request->all())) {
            PaymobLogger::warning(PaymobLogEvents::WEBHOOK_REJECTED, array_merge($this->logContext($request, $payload), [
                'reason' => 'invalid_hmac',
            ]));

            abort(403, 'Invalid Paymob webhook signature.');
        }

        if (! PaymobClient::recordWebhookEvent($payload, $request->all())) {
            PaymobLogger::info(PaymobLogEvents::WEBHOOK_DUPLICATE, $this->logContext($request, $payload));

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

        PaymobLogger::info(PaymobLogEvents::WEBHOOK_ACCEPTED, $this->logContext($request, $payload));

        return response()->json(['message' => 'Webhook received.']);
    }

    private function logContext(Request $request, array $payload): array
    {
        $context = [
            'operation' => 'webhook',
            'correlation_id' => $request->headers->get('X-Correlation-ID')
                ?: $request->headers->get('X-Request-ID'),
            'merchant_reference' => (string) data_get($payload, 'obj.order.id'),
            'status' => data_get($payload, 'obj.success'),
            'transaction_id' => data_get($payload, 'obj.id'),
        ];

        if (config('paymob.logging.include_payloads', false) === true) {
            $context['webhook_payload'] = $payload;
        }

        return array_filter($context, fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
