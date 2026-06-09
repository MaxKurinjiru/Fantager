<?php

declare(strict_types=1);

namespace App\Service\Sentry;

use Sentry\Event;
use Sentry\EventHint;

class SentryBeforeSendCallback
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'plainpassword',
        'token',
        'secret',
        'authorization',
        'cookie',
        'card',
        'cvv',
        'key',
        'session',
        'signature',
    ];

    public function __invoke(Event $event, ?EventHint $hint = null): ?Event
    {
        $request = $event->getRequest();

        if (empty($request)) {
            return $event;
        }

        // Scrub headers
        if (isset($request['headers']) && is_array($request['headers'])) {
            $request['headers'] = $this->scrubArray($request['headers']);
        }

        // Scrub cookies
        if (isset($request['cookies']) && is_array($request['cookies'])) {
            $request['cookies'] = $this->scrubArray($request['cookies']);
        }

        // Scrub request body data
        if (isset($request['data']) && is_array($request['data'])) {
            $request['data'] = $this->scrubArray($request['data']);
        }

        $event->setRequest($request);

        return $event;
    }

    /**
     * Recursive scrubbing function.
     *
     * @param array<string|int, mixed> $data
     *
     * @return array<string|int, mixed>
     */
    private function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrubArray($value);
            }
        }

        return $data;
    }

    private function isSensitive(string $key): bool
    {
        $normalizedKey = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($normalizedKey, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
