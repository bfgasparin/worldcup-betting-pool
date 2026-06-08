<?php

namespace Tests\Feature\Mail;

use Illuminate\Mail\Transport\CloudflareTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CloudflareMailerTest extends TestCase
{
    public function test_the_cloudflare_mailer_resolves_to_the_cloudflare_transport(): void
    {
        config([
            'services.cloudflare.account_id' => 'test-account',
            'services.cloudflare.key' => 'test-key',
        ]);

        $transport = Mail::mailer('cloudflare')->getSymfonyTransport();

        $this->assertInstanceOf(CloudflareTransport::class, $transport);
        $this->assertSame('cloudflare', (string) $transport);
    }
}
