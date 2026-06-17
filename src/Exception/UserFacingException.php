<?php

declare(strict_types=1);

namespace App\Exception;

final class UserFacingException extends \DomainException
{
    /**
     * @param array<string, int|string|float> $parameters
     */
    public function __construct(
        string $translationKey,
        private readonly array $parameters = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($translationKey, 0, $previous);
    }

    public function getTranslationKey(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, int|string|float>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
