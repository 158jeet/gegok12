<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Secure Family Fee Payment</title>
    <link rel="stylesheet" href="{{ asset('tagore-erp.css') }}?v={{ filemtime(public_path('tagore-erp.css')) }}">
</head>
<body class="tg-app">
    <main class="tg-main" style="max-width:760px;margin:40px auto;padding:20px">
        <section class="tg-card">
            <div class="tg-kpi-label">Tagore Group · Family checkout</div>
            <h1 style="margin:6px 0">Secure family fee payment</h1>
            <p style="color:var(--tg-muted,#64748b)">One payment will be allocated to the selected fee balances for all children listed below.</p>
            <p><strong>Parent account:</strong> {{ $order['parent_name'] }}</p>
            <div class="tg-table-wrap">
                <table>
                    <thead><tr><th>Child</th><th>Institution</th><th>Amount</th></tr></thead>
                    <tbody>
                    @foreach($order['children'] as $child)
                        <tr>
                            <td>{{ $child['name'] }}</td>
                            <td>{{ $child['institution'] }}</td>
                            <td>₹{{ number_format($child['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-top:20px">
                <div>
                    <div class="tg-kpi-label">Total family payment</div>
                    <strong style="font-size:32px">₹{{ number_format($order['amount'], 2) }}</strong>
                </div>
                <button id="pay-family" class="tg-btn primary" type="button">Proceed to secure payment</button>
            </div>
            <p id="payment-status" role="status" aria-live="polite" style="margin-top:14px;color:var(--tg-muted,#64748b)"></p>
            <p style="font-size:13px;color:var(--tg-muted,#64748b)">Do not refresh or start another checkout while the payment window is open. Active checkouts temporarily reserve the selected balances.</p>
            <a class="tg-btn" href="{{ route('tagore.parent.dashboard') }}">Return to family dashboard</a>
        </section>
    </main>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const payButton = document.getElementById('pay-family');
        const statusNode = document.getElementById('payment-status');
        const options = {
            key: @json($order['key']),
            amount: {{ (int) round($order['amount'] * 100) }},
            currency: 'INR',
            name: 'Tagore Group',
            description: 'Family fee payment',
            order_id: @json($order['gateway_order_id']),
            prefill: { name: @json($order['parent_name']) },
            notes: { family_payment_group_id: @json((string) $order['group_id']) },
            handler: function (response) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = @json(route('tagore.payments.family.confirm'));
                const fields = [
                    ['razorpay_order_id', response.razorpay_order_id],
                    ['razorpay_payment_id', response.razorpay_payment_id],
                    ['razorpay_signature', response.razorpay_signature],
                    ['_token', @json(csrf_token())]
                ];
                fields.forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            },
            modal: {
                ondismiss: function () {
                    statusNode.textContent = 'Payment window closed. The checkout reservation will expire automatically if payment was not completed.';
                    payButton.disabled = false;
                }
            }
        };

        payButton.addEventListener('click', function () {
            if (!window.Razorpay) {
                statusNode.textContent = 'The secure payment window could not load. Please check your connection and retry.';
                return;
            }
            payButton.disabled = true;
            statusNode.textContent = 'Opening secure payment window…';
            const checkout = new Razorpay(options);
            checkout.on('payment.failed', function () {
                statusNode.textContent = 'Payment was not completed. No fee ledger balance has been changed.';
                payButton.disabled = false;
            });
            checkout.open();
        });
    </script>
</body>
</html>
