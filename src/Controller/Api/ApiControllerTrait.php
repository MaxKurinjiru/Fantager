<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Translation\UserMessageTranslator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Service\Attribute\Required;

trait ApiControllerTrait
{
    private UserMessageTranslator $userMessages;

    #[Required]
    public function setUserMessageTranslator(UserMessageTranslator $userMessages): void
    {
        $this->userMessages = $userMessages;
    }

    /**
     * @param array<string, int|string|float> $parameters
     */
    protected function transMessage(string $key, array $parameters = []): string
    {
        return $this->userMessages->trans($key, $parameters);
    }

    /**
     * @param array<string, int|string|float> $parameters
     */
    protected function jsonError(string $key, int $status = 400, array $parameters = []): JsonResponse
    {
        return $this->json(['error' => $this->transMessage($key, $parameters)], $status);
    }

    protected function jsonException(\Throwable $exception, int $status = 422): JsonResponse
    {
        return $this->json(['error' => $this->userMessages->fromException($exception)], $status);
    }
}
