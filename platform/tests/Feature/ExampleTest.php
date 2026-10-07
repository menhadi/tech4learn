<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_host_is_not_served(): void
    {
        $this->get('https://unknown.test/')
            ->assertNotFound();
    }
}
