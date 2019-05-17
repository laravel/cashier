<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ __('Payment Confirmation') }} - {{ config('app.name', 'Laravel') }}</title>

    <script src="https://js.stripe.com/v3"></script>
</head>
<body>
    <div>
        <h1>{{ __('Payment Confirmation') }}</h1>

        @if ($paymentIntent->isSucceeded())
            <p>{{ __('The payment was successful.') }}</p>
        @elseif ($paymentIntent->isCancelled())
            <p>{{ __('The payment was cancelled.') }}</p>
        @else
            <p>{{ __('Please confirm your payment by filling out your payment details below.') }}</p>

            <div id="payment-elements">
                <input id="cardholder-name" type="text" placeholder="Name">
                <div id="card-element"></div>

                <button id="card-button">
                    {{ __('Submit Payment') }}
                </button>
            </div>

            <p id="message"></p>

            <script>
                const paymentElements = document.getElementById('payment-elements');
                const cardholderName = document.getElementById('cardholder-name');
                const cardButton = document.getElementById('card-button');
                const message = document.getElementById('message');

                const stripe = Stripe('{{ $stripeKey }}');
                const elements = stripe.elements();
                const cardElement = elements.create('card');
                cardElement.mount('#card-element');

                cardButton.addEventListener('click', function() {
                    stripe.handleCardPayment(
                        '{{ $paymentIntent->clientSecret() }}', cardElement, {
                            payment_method_data: {
                                billing_details: { name: cardholderName.value }
                            }
                        }
                    ).then(function (result) {
                        if (result.error) {
                            message.innerText = 'Error: '+result.error.message;
                        } else {
                            paymentElements.style.display = 'none';

                            message.innerText = '{{ __('The payment was successful.') }}';
                        }
                    });
                });
            </script>
        @endif

        <a href="{{ $redirect ?? url('/') }}">
            {{ __('Back') }}
        </a>
    </div>
</body>
</html>