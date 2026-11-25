<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_renders_the_school_portal_homepage(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertSee('School Grading Management System');
    }
}
