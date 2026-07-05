# Paymob Endpoints

## Scope

This document covers the Paymob payment flow end to end:

1. Authentication
2. Payment Intention / Order Registration
3. Payment Key Request
4. Transaction Callback
5. Webhook / Response Callback

> **Note:** Paymob has two integration styles:
>
> * **New flow:** Create a payment intention and use the returned `client_secret`.
> * **Classic flow:** Authenticate, register an order, then request a payment key/token.
>
> Do not mix both flows in the same implementation unless there is a clear business reason.

---

## Base URLs

| Environment  | Base URL                    |
| ------------ | --------------------------- |
| Egypt        | `https://accept.paymob.com` |
| Oman         | `https://oman.paymob.com`   |
| Saudi Arabia | `https://ksa.paymob.com`    |
| UAE          | `https://uae.paymob.com`    |

---

## 1. Authentication

| Field                      | Value                         |
| -------------------------- | ----------------------------- |
| Method                     | `POST`                        |
| Path                       | `Base URL + /api/auth/tokens` |
| Required inputs            | `api_key`                     |
| Meaningful response fields | `token`                       |

### Notes

* This step is mainly used in the classic Paymob Accept API flow.
* The returned `token` is used as `auth_token` in later classic API requests.

---

## 2. Payment Intention / Order Registration

| Field                      | Value                                                                                                     |
| -------------------------- | --------------------------------------------------------------------------------------------------------- |
| Method                     | `POST`                                                                                                    |
| Path                       | `Base URL + /v1/intention/`                                                                               |
| Required inputs            | `amount`, `currency`, `payment_methods`, `items`, `billing_data`                                          |
| Meaningful response fields | `id`, `client_secret`, `intention_order_id`, `amount`, `currency`, `status`, `payment_methods`, `created` |

### Request Inputs

| Field               | Required               | Description                                                                            |
| ------------------- | ---------------------- | -------------------------------------------------------------------------------------- |
| `amount`            | Yes                    | Total amount in the smallest currency unit. For EGP, `50000` means `500.00 EGP`.       |
| `currency`          | Yes                    | Payment currency. Example: `EGP`.                                                      |
| `payment_methods`   | Yes                    | Array of Paymob integration IDs or supported payment method identifiers.               |
| `items`             | Yes                    | Payment items. For registration, this can include one item such as `Registration Fee`. |
| `billing_data`      | Yes                    | Customer billing data.                                                                 |
| `customer`          | Optional / Recommended | Customer identity data.                                                                |
| `special_reference` | Recommended            | Your internal registration, order, or payment reference.                               |
| `notification_url`  | Recommended            | Backend callback URL that Paymob calls after payment processing.                       |
| `redirection_url`   | Recommended            | URL where the customer is redirected after checkout.                                   |

### Meaningful Response Fields

| Field                              | Type      | Description                                                                                        |
| ---------------------------------- | --------- | -------------------------------------------------------------------------------------------------- |
| `id`                               | `string`  | Unique Paymob intention ID. Save this locally to track the payment intention.                      |
| `client_secret`                    | `string`  | Secret used by the frontend to open Paymob Unified Checkout or Pixel for this payment intention.   |
| `intention_order_id`               | `integer` | Paymob internal order ID related to the intention. Useful for reconciliation and webhook matching. |
| `amount`                           | `integer` | Total payment amount in the smallest currency unit.                                                |
| `currency`                         | `string`  | Payment currency, for example `EGP`.                                                               |
| `status`                           | `string`  | Current intention status. `intended` means the payment was created but not completed yet.          |
| `payment_methods`                  | `array`   | Payment methods available for this intention.                                                      |
| `payment_methods[].integration_id` | `integer` | Paymob integration ID for the payment method.                                                      |
| `payment_methods[].name`           | `string`  | Human-readable payment method name, for example `Card`.                                            |
| `payment_methods[].method_type`    | `string`  | Payment method type, for example `card`.                                                           |
| `payment_methods[].currency`       | `string`  | Currency supported by the payment method.                                                          |
| `created`                          | `string`  | Date and time when the intention was created.                                                      |

### Example Response

```json
{
  "id": "01HYZK7XW3J5P8M5R4Q3T9V0E1",
  "client_secret": "egy_csk_01HYZK7XW3J5P8M5R4Q3T9V0E1",
  "intention_order_id": 987654321,
  "amount": 50000,
  "currency": "EGP",
  "status": "intended",
  "payment_methods": [
    {
      "integration_id": 123456,
      "name": "Card",
      "method_type": "card",
      "currency": "EGP"
    }
  ],
  "created": "2026-05-24T14:32:11Z"
}
```

### Notes

* Return `client_secret` to the frontend to start the checkout flow.
* Do not treat `status = intended` as a successful payment.
* Confirm payment only after receiving and verifying Paymob’s backend callback/webhook.
* Store `id`, `client_secret`, `intention_order_id`, `amount`, `currency`, and `status` in your local payment or order table.

---

## 3. Payment Key Request

| Field                      | Value                                                                                  |
| -------------------------- | -------------------------------------------------------------------------------------- |
| Method                     | `POST`                                                                                 |
| Path                       | `Base URL + /api/acceptance/payment_keys`                                              |
| Required inputs            | `auth_token`, `amount_cents`, `currency`, `order_id`, `integration_id`, `billing_data` |
| Meaningful response fields | `token`                                                                                |

### Request Inputs

| Field            | Required | Description                                                                        |
| ---------------- | -------- | ---------------------------------------------------------------------------------- |
| `auth_token`     | Yes      | Authentication token returned from `/api/auth/tokens`.                             |
| `amount_cents`   | Yes      | Total amount in cents/smallest currency unit. For EGP, `50000` means `500.00 EGP`. |
| `currency`       | Yes      | Payment currency. Example: `EGP`.                                                  |
| `order_id`       | Yes      | Paymob order ID returned from the order registration step.                         |
| `integration_id` | Yes      | Paymob integration ID for the selected payment method.                             |
| `billing_data`   | Yes      | Customer billing data required by Paymob.                                          |

### Meaningful Response Fields

| Field   | Type     | Description                                                               |
| ------- | -------- | ------------------------------------------------------------------------- |
| `token` | `string` | Payment key token used to open the Paymob iframe or classic checkout URL. |

### Example Response

```json
{
  "token": "PAYMENT_KEY_TOKEN"
}
```

### Notes

* This endpoint belongs to the classic Paymob Accept API flow.
* The returned `token` is used as the `payment_token`.
* If you are using the new Payment Intention flow, you usually use `client_secret` instead of `payment_key`.

---

## 4. Transaction Callback

| Field                      | Value                                                                                                                                                                                                               |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Method                     | `POST`                                                                                                                                                                                                              |
| Path                       | Your configured **Transaction Processed Callback URL** in Paymob Dashboard. Example: `/api/webhooks/paymob/transaction`                                                                                             |
| Required inputs            | Paymob sends a transaction callback payload containing transaction data and an `hmac`.                                                                                                                              |
| Meaningful response fields | Your endpoint should return `200 OK` after successful processing. Meaningful incoming fields include transaction ID, payment status, amount, currency, order data, integration ID, payment source data, and `hmac`. |

### Incoming Payload Fields

| Field                         | Description                                                                  |
| ----------------------------- | ---------------------------------------------------------------------------- |
| `type`                        | Callback type. Usually related to the transaction event.                     |
| `obj.id`                      | Paymob transaction ID.                                                       |
| `obj.success`                 | Indicates whether the transaction succeeded.                                 |
| `obj.pending`                 | Indicates whether the transaction is still pending.                          |
| `obj.amount_cents`            | Paid amount in the smallest currency unit.                                   |
| `obj.currency`                | Payment currency.                                                            |
| `obj.order.id`                | Paymob order ID.                                                             |
| `obj.order.merchant_order_id` | Your merchant/internal order reference, if available.                        |
| `obj.integration_id`          | Paymob integration ID used for the payment.                                  |
| `obj.is_auth`                 | Indicates whether the transaction is an authorization.                       |
| `obj.is_capture`              | Indicates whether the transaction is a capture.                              |
| `obj.is_voided`               | Indicates whether the transaction was voided.                                |
| `obj.is_refunded`             | Indicates whether the transaction was refunded.                              |
| `obj.error_occured`           | Indicates whether an error occurred.                                         |
| `obj.source_data.type`        | Payment source type, such as card or wallet.                                 |
| `obj.source_data.pan`         | Masked card or payment source value, when available.                         |
| `obj.source_data.sub_type`    | Payment source subtype, when available.                                      |
| `obj.data.message`            | Gateway or transaction message, when available.                              |
| `hmac`                        | Hash used to verify that the callback came from Paymob and was not modified. |

### Notes

* Always verify the `hmac` before trusting the callback.
* This callback should be the source of truth for payment confirmation.
* Do not confirm the order using only the frontend redirect result.
* Make your webhook processing idempotent to avoid duplicate updates if Paymob retries the callback.

---

## 5. Webhook / Response Callback

| Field                      | Value                                                                                                                                                                         |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Method                     | `POST` for backend processed callback, or redirect/return flow for customer response callback                                                                                 |
| Path                       | Backend webhook: your configured **Transaction Processed Callback URL**. Customer redirect: your configured **Transaction Response Callback / Redirection URL**.              |
| Required inputs            | Backend webhook receives transaction data and `hmac`. Customer response callback is used to redirect the customer back to your platform after payment.                        |
| Meaningful response fields | Backend webhook should return `200 OK` after successful processing. Customer response callback is used mainly to display success, failure, or pending status to the customer. |

### Important Difference

| Callback Type                  | Purpose                                                    | Should update payment status?                               |
| ------------------------------ | ---------------------------------------------------------- | ----------------------------------------------------------- |
| Transaction Processed Callback | Server-to-server callback from Paymob to your backend.     | Yes. Use it as the source of truth after HMAC verification. |
| Transaction Response Callback  | Customer redirect back to your website/app after checkout. | No. Use it mainly for user experience.                      |

### Notes

* `notification_url` is used for the backend transaction processed callback.
* `redirection_url` is used for redirecting the customer after checkout.
* The backend callback is the reliable place to update your order/payment status.
* The redirect callback can be used to show a confirmation screen, but it should not be trusted alone.

---

## General Notes

* Keep request and response fields aligned with the actual implementation.
* Store Paymob IDs locally for reconciliation and debugging.
* Always verify HMAC before updating payment or order status.
* Do not treat a created intention, generated payment key, or redirect response as proof of successful payment.
* Confirm the final payment result only after the backend callback is received and verified.
* Add real request/response examples under each section once the implementation is finalized.
