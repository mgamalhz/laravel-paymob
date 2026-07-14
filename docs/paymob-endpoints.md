# Paymob Endpoints

## Scope

This document covers the classic Paymob Accept flow used by this package:

1. Authentication
2. Order Registration
3. Payment Key Request
4. Transaction Callback
5. Webhook / Response Callback

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

* This is the first step in the classic flow.
* The returned `token` is used as `auth_token` in later requests.

---

## 2. Order Registration

| Field                      | Value                                                                 |
| -------------------------- | --------------------------------------------------------------------- |
| Method                     | `POST`                                                                |
| Path                       | `Base URL + /api/ecommerce/orders`                                    |
| Required inputs            | `auth_token`, `delivery_needed`, `amount_cents`, `currency`, `items` |
| Meaningful response fields | `id`, `created_at`                                                    |

### Request Inputs

| Field               | Required               | Description                                                                        |
| ------------------- | ---------------------- | ---------------------------------------------------------------------------------- |
| `auth_token`        | Yes                    | Authentication token returned from `/api/auth/tokens`.                             |
| `delivery_needed`   | Yes                    | Boolean flag used by Paymob order creation.                                        |
| `amount_cents`      | Yes                    | Total amount in cents/smallest currency unit. For EGP, `50000` means `500.00 EGP`. |
| `currency`          | Yes                    | Payment currency. Example: `EGP`.                                                  |
| `items`             | Yes                    | Array of order items.                                                              |
| `merchant_order_id` | Optional / Recommended | Your internal order reference.                                                     |

### Meaningful Response Fields

| Field        | Type      | Description                               |
| ------------ | --------- | ----------------------------------------- |
| `id`         | `integer` | Paymob order ID used in later requests.   |
| `created_at` | `string`  | Date and time when the order was created. |

### Example Response

```json
{
  "id": 987654321,
  "created_at": "2026-05-24T14:32:11Z"
}
```

### Notes

* This endpoint belongs to the classic Accept API flow.
* Save the returned `id` and use it in the payment key request.
* Do not mix this endpoint with the newer intention flow.

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

* This endpoint belongs to the classic Accept API flow.
* The returned `token` is used as the `payment_token`.

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

* Keep request and response fields aligned with the classic Accept API flow.
* Store Paymob IDs locally for reconciliation and debugging.
* Always verify HMAC before updating payment or order status.
* Do not treat a created order, generated payment key, or redirect response as proof of successful payment.
* Confirm the final payment result only after the backend callback is received and verified.
* Add real request/response examples under each section once the implementation is finalized.
