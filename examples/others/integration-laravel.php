<?php

declare(strict_types=1);

/**
 * Example 11: Laravel Integration
 * 
 * Demonstrates how to structure NexusAI into a modern Laravel project.
 * You typically want to perform setup in a ServiceProvider so the static
 * facade is ready to use anywhere in controllers, jobs, and commands.
 */

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Token27\NexusAI\NexusAI;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Http\Discovery\Psr18ClientDiscovery;

class NexusAIServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Configure the Providers from the 'config/nexus-ai.php' file
        // 
        // Example configuration file (`config/nexus-ai.php`):
        // return [
        //     'providers' => [
        //         'openai' => [
        //             'api_key' => env('OPENAI_API_KEY'),
        //         ],
        //         'anthropic' => [
        //             'api_key' => env('ANTHROPIC_API_KEY'),
        //         ]
        //     ]
        // ];
        $config = config('nexus-ai.providers', []);
        NexusAI::configure($config);

        // 2. Inject Laravel's HTTP Client seamlessly
        // Alternatively, let NexusAI discover Guzzle natively.
        // It uses `php-http/discovery` under the hood out of the box.
        // NexusAI::setHttpClient(new \GuzzleHttp\Client());

        // 3. Register global middlewares (Optional)
        // E.g., Adding retries on production
        if (app()->environment('production')) {
            NexusAI::withMiddleware(new \Token27\NexusAI\Pipeline\Middleware\RetryMiddleware(
                maxRetries: 3,
                baseDelayMs: 1000,
                multiplier: 1.5,
                jitter: true
            ));
        }
    }
}

// ---------------------------------------------------------
// Example Controller Usage (app/Http/Controllers/AiController.php):
/*
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Token27\NexusAI\NexusAI;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class AiController extends Controller
{
    public function generate(Request $request)
    {
        $response = NexusAI::using('openai', 'gpt-4o')
            ->withPrompt($request->input('prompt', 'Hello!'))
            ->asText();

        return response()->json([
            'text' => $response->text,
            'tokens' => $response->usage->totalTokens(),
        ]);
    }
}
*/



