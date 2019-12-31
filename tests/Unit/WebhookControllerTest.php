<?php

namespace Laravel\Cashier\Tests\Unit;

use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;
use PHPUnit\Framework\TestCase;

class WebhookControllerTest extends TestCase
{
    public function testProperMethodsAreCalledBasedOnStripeEvent()
    {
        $_SERVER['__received'] = false;
        $request = Request::create(
            '/', 'POST', [], [], [], [], json_encode(['type' => 'charge.succeeded', 'id' => 'event-id'])
        );

        (new WebhookControllerTestStub)->handleWebhook($request);

        $this->assertTrue($_SERVER['__received']);
    }

    public function testNormalResponseIsReturnedIfMethodIsMissing()
    {
        $request = Request::create(
            '/', 'POST', [], [], [], [], json_encode(['type' => 'foo.bar', 'id' => 'event-id'])
        );

        $response = (new WebhookControllerTestStub)->handleWebhook($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}

class WebhookControllerTestStub extends WebhookController
{
    public function __construct()
    {
        // Don't call parent constructor to prevent setting middleware...
    }

    public function handleChargeSucceeded()
    {
        $_SERVER['__received'] = true;
    }
}
