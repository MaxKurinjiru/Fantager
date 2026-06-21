<?php

declare(strict_types=1);

namespace App\Service\Translation;

use App\Entity\Auth\User;
use App\Exception\UserFacingException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserMessageTranslator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, int|string|float> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $locale = null, string $domain = 'messages'): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale ?? $this->getLocale());
    }

    /**
     * @param array<string, int|string|float> $parameters
     */
    public function transForUser(string $id, User $user, array $parameters = [], string $domain = 'messages'): string
    {
        return $this->trans($id, $parameters, $user->getLocale(), $domain);
    }

    public function fromException(\Throwable $exception, ?string $locale = null): string
    {
        if ($exception instanceof UserFacingException) {
            return $this->trans($exception->getTranslationKey(), $exception->getParameters(), $locale);
        }

        $message = $exception->getMessage();
        if ($this->isTranslationKey($message)) {
            return $this->trans($message, [], $locale);
        }

        return $message;
    }

    public function getLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'cs';
    }

    private function isTranslationKey(string $message): bool
    {
        return (bool) preg_match('/^(error|flash|notification|calendar)\.[a-z0-9_.]+$/', $message);
    }
}
