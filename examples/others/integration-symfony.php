<?php

declare(strict_types=1);

/**
 * Example 12: Symfony Integration
 * 
 * Demonstrates how to properly bootstrap the statically configured NexusAI 
 * within the Symfony Framework's Event Subscriber or Kernel logic.
 */

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Token27\NexusAI\NexusAI;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class NexusAIConfigurator implements EventSubscriberInterface
{
    /**
     * Pass necessary parameters defined in services.yaml (e.g. from .env)
     * 
     * services.yaml:
     *   App\EventSubscriber\NexusAIConfigurator:
     *     arguments:
     *       $openAiKey: '%env(OPENAI_API_KEY)%'
     */
    public function __construct(
        private readonly string $openAiKey,
        private readonly ?string $anthropicKey = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Running at the very beginning of the Request Lifecycle
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    /**
     * Apply configurations securely to NexusAI
     */
    public function onKernelRequest(): void
    {
        $providers = [
            'openai' => [
                'api_key' => $this->openAiKey,
            ]
        ];

        if ($this->anthropicKey) {
            $providers['anthropic'] = [
                'api_key' => $this->anthropicKey,
            ];
        }

        NexusAI::configure($providers);

        // Example: Utilizing Symfony's HttpClient specifically if needed:
        // Note: The HTTP client must implement PSR-18 ClientInterface.
        // NexusAI::setHttpClient($symfonyPsr18Client);
    }
}

// ---------------------------------------------------------
// Example Controller Usage (src/Controller/AiController.php):
/*
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Token27\NexusAI\NexusAI;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class AiController extends AbstractController
{
    #[Route('/api/generate', name: 'app_ai_generate')]
    public function generate(): JsonResponse
    {
        $response = NexusAI::using('openai', 'gpt-4o')
            ->withSystemPrompt('You are a Symfony Expert.')
            ->withPrompt('Why should I use Symfony compiler passes?')
            ->asText();

        return new JsonResponse([
            'result' => $response->text
        ]);
    }
}
*/



