<?php

namespace Laravel\Cashier\Exceptions;

use Laravel\Cashier\Subscription;
use Laravel\Cashier\PaymentIntent;

class ActionRequired extends IncompletePayment
{
    public static function forSubscription(Subscription $subscription, PaymentIntent $paymentIntent)
    {
        return new static(
            $paymentIntent,
            "The payment attempt to pay for a subscription \"{$subscription->stripe_id}\" with plan \"{$subscription->stripe_plan}\" needs an extra action before it can be completed."
        );
    }
}
