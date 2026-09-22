<?php

namespace Paymob\Laravel\Support;

final class PaymobLogEvents
{
    public const REQUEST = 'paymob.request';

    public const RESPONSE = 'paymob.response';

    public const RETRY = 'paymob.retry';

    public const FAILURE = 'paymob.failure';

    public const WEBHOOK_RECEIVED = 'paymob.webhook.received';

    public const WEBHOOK_ACCEPTED = 'paymob.webhook.accepted';

    public const WEBHOOK_REJECTED = 'paymob.webhook.rejected';

    public const WEBHOOK_DUPLICATE = 'paymob.webhook.duplicate';
}
