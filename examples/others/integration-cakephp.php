<?php

declare(strict_types=1);

/**
 * Example 13: CakePHP Integration
 * 
 * Demonstrates how to bootstrap NexusAI at application startup within 
 * the CakePHP framework natively relying on the `Configure` utility.
 */

namespace App\Application;

use Cake\Core\Configure;
use Cake\Http\BaseApplication;
use Token27\NexusAI\NexusAI;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Token27\NexusAI\Pricing\Engine\PricingEngine;

class Application extends BaseApplication
{
    /**
     * Initialization hook.
     */
    public function bootstrap(): void
    {
        // Call parent to load bootstrap.php and configurations
        parent::bootstrap();

        // 1. You would place these configure statements in your config/app.php
        // Configure::write('Nexus', [
        //     'providers' => [
        //         'openai' => ['api_key' => env('OPENAI_API_KEY')],
        //     ],
        //     'budget' => 5.00
        // ]);

        // 2. Read existing configuration values dynamically
        $providers = Configure::read('Nexus.providers', []);

        if (!empty($providers)) {
            NexusAI::configure($providers);
        }

        // 3. (Optional) Inject tracking tools early in the app lifecycle
        $budgetLimit = Configure::read('Nexus.budget');
        if ($budgetLimit !== null) {
            NexusAI::withPricing(new PricingEngine(), $budgetLimit);
        }
    }
}

// ---------------------------------------------------------
// Example Controller Usage (src/Controller/ArticlesController.php):
/*
namespace App\Controller;

use Token27\NexusAI\NexusAI;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class ArticlesController extends AppController
{
    public function summarize(string $id)
    {
        $article = $this->Articles->get($id);

        $response = NexusAI::using('openai', 'gpt-4o-mini')
            ->withSystemPrompt('Provide a 1 paragraph summarization.')
            ->withPrompt($article->body)
            ->asText();

        $this->set([
            'summary' => $response->text,
            'cost' => $response->meta->get('_pricing_result')?->totalCostUsd() ?? 0,
        ]);
        $this->viewBuilder()->setOption('serialize', ['summary', 'cost']);
    }
}
*/



