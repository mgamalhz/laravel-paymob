<?php

namespace Paymob\Laravel;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Paymob\Laravel\DTO\PaymobWebhookPayload;
use Paymob\Laravel\Events\PaymobWebhookReceived;
use Paymob\Laravel\Models\Payment;
use Paymob\Laravel\Support\PaymobLogEvents;
use Paymob\Laravel\Support\PaymobLogger;

class PayMobWebHockController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->run($request);
    }

    public function run(Request $request): JsonResponse
    {
        $input = $request->json()->all();
        $signature = $request->query('hmac', $input['hmac'] ?? null);
        if (! PaymobClient::checkHmac($input, incomingHmac: is_string($signature) ? $signature : '')) {
            abort(403, 'Invalid Paymob webhook signature.');
        }

        $payload = validator($input, [
            'obj' => ['required', 'array'],
            'obj.id' => ['required', 'integer'],
            'obj.amount_cents' => ['required', 'integer', 'min:1'],
            'obj.order.id' => ['required', 'integer'],
            'obj.success' => ['required', 'boolean'],
            'obj.is_auth' => ['required', 'boolean'],
            'obj.pending' => ['required', 'boolean'],
            'obj.is_refunded' => ['required', 'boolean'],
            'obj.is_voided' => ['required', 'boolean'],
            'obj.error_occured' => ['required', 'boolean'],

        ])->validate();

        PaymobLogger::info(PaymobLogEvents::WEBHOOK_RECEIVED, $this->logContext($request, $payload));

        $processed = DB::transaction(function () use ($payload): bool {
            $payment = Payment::query()
                ->where('paymob_reference', (string) data_get($payload, 'obj.order.id'))
                ->lockForUpdate()->first();
            abort_if($payment === null, 404, 'Unknown payment.');
            abort_unless($payment->amount_cents === (int) data_get($payload, 'obj.amount_cents'), 409, 'Payment amount mismatch.');
            if ($payment->status !== 'processing') {
                return false;
            }
            foreach (['pending', 'is_refunded', 'is_voided', 'error_occured', 'is_auth'] as $flag) {
                if (data_get($payload, 'obj.'.$flag) === true) {
                    return false;
                }
            }
            if (! PaymobClient::recordWebhookEvent($payload)) {
                return false;
            }
            $payment->forceFill([
                'status' => data_get($payload, 'obj.success') ? 'paid' : 'failed',
                'transaction_id' => (int) data_get($payload, 'obj.id'),
            ])->save();
            event(new PaymobWebhookReceived($this->webhookPayload($payload)));

            return true;
        });
        if (! $processed) {
            PaymobLogger::info(PaymobLogEvents::WEBHOOK_DUPLICATE, $this->logContext($request, $payload));

            return response()->json(['message' => 'Webhook already processed.']);
        }

        PaymobLogger::info(PaymobLogEvents::WEBHOOK_ACCEPTED, $this->logContext($request, $payload));

        return response()->json(['message' => 'Webhook received.']);
    }

    private function webhookPayload(array $payload): PaymobWebhookPayload
    {
        return new PaymobWebhookPayload(
            transactionId: (string) data_get($payload, 'obj.id'),
            orderId: (string) data_get($payload, 'obj.order.id'),
            amountCents: (int) data_get($payload, 'obj.amount_cents'),
            status: (bool) data_get($payload, 'obj.success') ? 'paid' : 'failed',
            verifiedAt: new DateTimeImmutable,
        );
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

        return array_filter($context, fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
