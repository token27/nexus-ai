<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Token27\NexusAI\Enum\Provider;

final class ProviderTest extends TestCase
{
    public function testAllProvidersHaveBaseUrl(): void
    {
        foreach (Provider::cases() as $provider) {
            $this->assertNotEmpty($provider->baseUrl(), "Provider {$provider->value} must have a base URL");
        }
    }

    public function testOpenAIBaseUrl(): void
    {
        $this->assertSame('https://api.openai.com/v1', Provider::OpenAI->baseUrl());
    }

    public function testAnthropicBaseUrl(): void
    {
        $this->assertStringContainsString('anthropic', Provider::Anthropic->baseUrl());
    }

    public function testFromString(): void
    {
        $this->assertSame(Provider::OpenAI, Provider::from('openai'));
        $this->assertSame(Provider::Anthropic, Provider::from('anthropic'));
    }
}
