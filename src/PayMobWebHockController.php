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
        if (! PaymobClient::checkHmac($request->all())) {
            abort(403, 'Invalid Paymob webhook signature.');
        }

        event(new PaymobWebhookReceived($this->payloadFrom($request)));

        return response()->json(['status' => 'ok']);
    }

    private function payloadFrom(Request $request): PaymobWebhookPayload
    {
        $payload = $request->input('obj', $request->all());

        return new PaymobWebhookPayload(
            transactionId: (string) data_get($payload, 'id'),
            orderId: (string) data_get($payload, 'order.id'),
            amountCents: (int) data_get($payload, 'amount_cents'),
            status: $this->statusFrom($payload),
            verifiedAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function statusFrom(array $payload): string
    {
        if ((bool) data_get($payload, 'success')) {
            return 'paid';
        }

        if ((bool) data_get($payload, 'pending')) {
            return 'pending';
        }

        return 'failed';
    }
}
