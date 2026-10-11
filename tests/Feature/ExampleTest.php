<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health(): void
    {
        $this->get('/up')->assertOk();
    }
}
