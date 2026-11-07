<?php

namespace Tests\Feature;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_can_subscribe(): void
    {
        $this->postJson('/api/newsletter', ['email' => 'Ada@Example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Thanks! You are subscribed.');

        $this->assertSame('ada@example.com', Subscriber::sole()->email);
    }

    public function test_existing_subscribers_get_the_same_response_and_no_duplicate(): void
    {
        $first = $this->postJson('/api/newsletter', ['email' => 'ada@example.com'])->assertOk()->json();
        $second = $this->postJson('/api/newsletter', ['email' => 'ADA@example.com'])->assertOk()->json();

        $this->assertSame($first, $second);
        $this->assertSame(1, Subscriber::count());
    }

    public function test_invalid_emails_are_rejected(): void
    {
        $this->postJson('/api/newsletter', ['email' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Please enter a valid email.');

        $this->assertSame(0, Subscriber::count());
    }

    public function test_honeypot_submissions_are_dropped(): void
    {
        $this->postJson('/api/newsletter', ['email' => 'bot@example.com', 'website' => 'x'])->assertOk();

        $this->assertSame(0, Subscriber::count());
    }
}
