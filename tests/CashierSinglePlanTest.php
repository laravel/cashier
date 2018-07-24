<?php

namespace Laravel\Cashier\Tests;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Cashier\Tests\Fixtures\User;
use Laravel\Cashier\Tests\Fixtures\CashierTestControllerStub;

/**
 * Class CashierSinglePlanTest.
 */
final class CashierSinglePlanTest extends CashierBaseTest
{
    /**
     * Tests.
     */
    public function testSubscriptionsCanBeCreated()
    {
        /** @var User $user */
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')->create($this->getTestToken());

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull($user->subscription('main')->stripe_id);

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribedToPlan('monthly-10-1', 'main'));
        $this->assertFalse($user->subscribedToPlan('monthly-10-1', 'something'));
        $this->assertFalse($user->subscribedToPlan('monthly-10-2', 'main'));
        $this->assertTrue($user->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($user->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($user->subscription('main')->active());
        $this->assertFalse($user->subscription('main')->cancelled());
        $this->assertFalse($user->subscription('main')->onGracePeriod());
        $this->assertTrue($user->subscription('main')->recurring());
        $this->assertFalse($user->subscription('main')->ended());

        // Cancel Subscription
        $subscription = $user->subscription('main');
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Modify Ends Date To Past
        $oldGracePeriod = $subscription->ends_at;
        $subscription->fill(['ends_at' => Carbon::now()->subDays(5)])->save();

        $this->assertFalse($subscription->active());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());

        $subscription->fill(['ends_at' => $oldGracePeriod])->save();

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Increment & Decrement
        $subscription->incrementQuantity();

        $this->assertEquals(2, $subscription->quantity);

        $subscription->decrementQuantity();

        $this->assertEquals(1, $subscription->quantity);

        // Swap Plan
        $subscription->swap('monthly-10-2');

        $this->assertEquals('monthly-10-2', $subscription->stripe_plan);

        // Invoice Tests
        $invoice = $user->invoices()[1];

        $this->assertEquals('$10.00', $invoice->total());
        $this->assertFalse($invoice->hasDiscount());
        $this->assertFalse($invoice->hasStartingBalance());
        $this->assertNull($invoice->coupon());
        $this->assertInstanceOf(Carbon::class, $invoice->date());
    }

    public function test_creating_subscription_with_coupons()
    {
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
             ->withCoupon('coupon-1')->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($user->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Invoice Tests
        $invoice = $user->invoices()[0];

        $this->assertTrue($invoice->hasDiscount());
        $this->assertEquals('$5.00', $invoice->total());
        $this->assertEquals('$5.00', $invoice->amountOff());
        $this->assertFalse($invoice->discountIsPercentage());
    }

    public function test_creating_subscription_with_an_anchored_billing_cycle()
    {
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
             ->anchorBillingCycleOn(new \DateTime('first day of next month'))
             ->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $this->assertTrue($user->subscribed('main'));
        $this->assertTrue($user->subscribed('main', 'monthly-10-1'));
        $this->assertFalse($user->subscribed('main', 'monthly-10-2'));
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Invoice Tests
        $invoice = $user->invoices()[0];
        $invoicePeriod = $invoice->invoiceItems()[0]->period;

        $this->assertEquals(
            (new \DateTime('now'))->format('Y-m-d'),
            date('Y-m-d', $invoicePeriod->start)
        );
        $this->assertEquals(
            (new \DateTime('first day of next month'))->format('Y-m-d'),
            date('Y-m-d', $invoicePeriod->end)
        );
    }

    public function test_generic_trials()
    {
        $user = new User;
        $this->assertFalse($user->onGenericTrial());
        $user->trial_ends_at = Carbon::tomorrow();
        $this->assertTrue($user->onGenericTrial());
        $user->trial_ends_at = Carbon::today()->subDays(5);
        $this->assertFalse($user->onGenericTrial());
    }

    public function test_creating_subscription_with_trial()
    {
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
             ->trialDays(7)->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);

        // Cancel Subscription
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::today()->addDays(7)->day, $subscription->trial_ends_at->day);
    }

    public function test_creating_subscription_with_explicit_trial()
    {
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
             ->trialUntil(Carbon::tomorrow()->hour(3)->minute(15))->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::tomorrow()->hour(3)->minute(15), $subscription->trial_ends_at);

        // Cancel Subscription
        $subscription->cancel();

        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());

        // Resume Subscription
        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
        $this->assertEquals(Carbon::tomorrow()->hour(3)->minute(15), $subscription->trial_ends_at);
    }

    public function test_applying_coupons_to_existing_customers()
    {
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        // Create Subscription
        $user->newSubscription('main', 'monthly-10-1')
             ->create($this->getTestToken());

        $user->applyCoupon('coupon-1');

        $customer = $user->asStripeCustomer();

        $this->assertEquals('coupon-1', $customer->discount->coupon->id);
    }

    /**
     * @group foo
     */
    public function test_marking_as_cancelled_from_webhook()
    {
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        $user->newSubscription('main', 'monthly-10-1')
             ->create($this->getTestToken());

        $subscription = $user->subscription('main');

        $request = Request::create(
            '/',
            'POST',
            [],
            [],
            [],
            [],
            json_encode(
                [
                    'id'   => 'foo',
                    'type' => 'customer.subscription.deleted',
                    'data' => [
                        'object' => [
                            'id'       => $subscription->stripe_id,
                            'customer' => $user->stripe_id,
                        ],
                    ],
                ]
            )
        );

        $controller = new CashierTestControllerStub;
        $response = $controller->handleWebhook($request);
        $this->assertEquals(200, $response->getStatusCode());

        $user = $user->fresh();
        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->cancelled());
    }

    public function testCreatingOneOffInvoices()
    {
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        // Create Invoice
        $user->createAsStripeCustomer($this->getTestToken());
        $user->invoiceFor('Laravel Cashier', 1000);

        // Invoice Tests
        $invoice = $user->invoices()[0];
        $this->assertEquals('$10.00', $invoice->total());
        $this->assertEquals('Laravel Cashier', $invoice->invoiceItems()[0]->asStripeInvoiceItem()->description);
    }

    public function testRefunds()
    {
        $user = User::create(
            [
                'email' => 'taylor@laravel.com',
                'name'  => 'Taylor Otwell',
            ]
        );

        // Create Invoice
        $user->createAsStripeCustomer($this->getTestToken());
        $invoice = $user->invoiceFor('Laravel Cashier', 1000);

        // Create the refund
        $refund = $user->refund($invoice->charge);

        // Refund Tests
        $this->assertEquals(1000, $refund->amount);
    }
}
