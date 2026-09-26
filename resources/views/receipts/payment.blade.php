<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt #{{ $payment->transaction_id }}</title>
    <style>
        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 14px;
            line-height: 1.45;
            margin: 40px;
        }

        h1 {
            font-size: 24px;
            margin: 0 0 24px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 0;
            text-align: left;
            vertical-align: top;
        }

        th {
            color: #4b5563;
            font-weight: 700;
            width: 34%;
        }
    </style>
</head>
<body>
<h1>Payment Receipt</h1>

<table>
    <tr>
        <th>Transaction ID</th>
        <td>{{ $payment->transaction_id }}</td>
    </tr>
    <tr>
        <th>Paymob Reference</th>
        <td>{{ $payment->paymob_reference }}</td>
    </tr>
    <tr>
        <th>Amount</th>
        <td>{{ number_format($payment->amount_cents / 100, 2) }}</td>
    </tr>
    <tr>
        <th>Status</th>
        <td>{{ $payment->status }}</td>
    </tr>
    <tr>
        <th>Captured At</th>
        <td>{{ optional($payment->captured_at)->toDateTimeString() }}</td>
    </tr>
</table>
</body>
</html>
