<?php

declare(strict_types=1);

/**
 * Example 14: Pure Dependency Injection
 * 
 * If you strongly prefer avoiding Static Facades (Singletons) in your architecture,
 * Nexus AI is fully built with composability in mind.
 * 
 * This example simulates how you would bind NexusAI components inside a
 * PSR-11 properly configured Dependency Injection Container, such as 
 * Laravel's App/Container, Symfony's Container, or PHP-DI.
 */

// We fake a DI container mechanism here just for illustration:
class Container
{
    private array $bindings = [];
    public function singleton(string $class, callable $factory): void
    {
        $this->bindings[$class] = function () use ($factory) {
            static $instance = null;
            if ($instance === null) {
                $instance = $factory($this);
            }
            return $instance;
        };
    }
    public function make(string $class)
    {
        return $this->bindings[$class]();
    }
}

require __DIR__ . '/../vendor/autoload.php';

use Token27\NexusAI\PendingRequest;
use Token27\NexusAI\Driver\DriverRegistry;
use Token27\NexusAI\Pipeline\Pipeline;
use Token27\NexusAI\Pipeline\Middleware\ValidationMiddleware;

$container = new Container();

// =====================================================================
// STEP 1: Register the core classes as Singletons in the Container
// =====================================================================

$container->singleton(DriverRegistry::class, function () {
    $registry = new DriverRegistry();
    // Configure API keys logically
    $registry->configure('openai', ['api_key' => 'sk-di-key-openai']);
    $registry->configure('anthropic', ['api_key' => 'sk-di-key-anthropic']);

    // ==============================================================
    // Explicit HTTP Dependency Injection
    // The library needs a PSR-18 Client and PSR-17 Factories.
    // ==============================================================

    // OPINIONATED OPTION: Explicitly inject Guzzle classes (Requires guzzlehttp/guzzle and guzzlehttp/psr7)
    $client = new \GuzzleHttp\Client();
    $requestFactory = new \GuzzleHttp\Psr7\HttpFactory();
    $registry->setHttpDependencies($client, $requestFactory, $requestFactory);

    // MAGIC OPTION: Automatically Discover HTTP capabilities (Requires php-http/discovery)
    // $registry->setHttpDependencies(
    //     \Http\Discovery\Psr18ClientDiscovery::find(),
    //     \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory(),
    //     \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory()
    // );

    return $registry;
});

$container->singleton(Pipeline::class, function () {
    $pipeline = new Pipeline();
    // Hook in generic middleware explicitly for every Request using pipe()
    // pipe() returns a new instance because the Pipeline is immutable!
    return $pipeline->pipe(new ValidationMiddleware());
});

// =====================================================================
// STEP 2: Building your service classes that rely on PendingRequest
// =====================================================================

class CustomerSupportService
{
    // Proper Dependency Injection via constructor!
    public function __construct(
        private readonly DriverRegistry $registry,
        private readonly Pipeline $pipeline
    ) {
    }

    public function autoRespond(string $userMsg): string
    {
        // Notice we instantiate the PendingRequest with our bound singletons
        // No static `NexusAI::using()` facade invoked anywhere, making this 
        // 100% Mockable and Testable in unit testing environments!
        $request = new PendingRequest(
            registry: $this->registry,
            pipeline: $this->pipeline,
            provider: 'openai',
            model: 'gpt-4o-mini'
        );

        $response = $request
            ->withSystemPrompt("You are a helpful customer support bot.")
            ->withPrompt($userMsg)
            ->asText();

        return $response->text;
    }
}

// Bind our Service class
$container->singleton(CustomerSupportService::class, function ($c) {
    return new CustomerSupportService(
        $c->make(DriverRegistry::class),
        $c->make(Pipeline::class)
    );
});

// =====================================================================
// STEP 3: Using your configured service
// =====================================================================

$service = $container->make(CustomerSupportService::class);

echo "Starting Support Interaction (using pure DI)...\n";
try {
    echo $service->autoRespond("How do I reset my password?");
} catch (\Exception $e) {
    // Note: This will naturally fail because the API key is fake in this example
    echo "Fails gracefully because API key is fake.\nError: " . $e->getMessage() . "\n";
}



