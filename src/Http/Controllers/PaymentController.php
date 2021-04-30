<?php

namespace Laravel\Cashier\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Middleware\VerifyRedirectUrl;
use Laravel\Cashier\Payment;
use Stripe\PaymentIntent as StripePaymentIntent;

class PaymentController extends Controller
{
    /**
     * Create a new PaymentController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(VerifyRedirectUrl::class);
    }

    /**
     * Display the form to gather additional payment verification for the given payment.
     *
     * @param  string  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function show($id)
    {
        $payment = new Payment(StripePaymentIntent::retrieve(
            ['id' => $id, 'expand' => ['payment_method']], Cashier::stripeOptions())
        );

        return view('cashier::payment', [
            'stripeKey' => config('cashier.key'),
            'amount' => $payment->amount(),
            'paymentIntent' => Arr::only($payment->asStripePaymentIntent()->toArray(), [
                'id', 'status', 'payment_method_types', 'client_secret',
            ]),
            'paymentMethod' => request('source_type', ''),
            'errorMessage' => request('redirect_status') === 'failed'
                ? 'Something went wrong when trying to confirm the payment. Please try again.'
                : '',
            'customer' => $payment->customer(),
            'redirect' => url(request('redirect', '/')),
        ]);
    }
}
