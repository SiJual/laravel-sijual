<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_or_redirect_response(): void
    {
        $response = $this->get('/');
        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
    }
}
