<?php

namespace App\Services\Providers;

final readonly class ProviderResult
{
    private function __construct(
        public bool $success,
        public bool $retryable,
        public ?string $error = null,
    ) {
    }

    public static function success(): self
    {
        return new self(true, false);
    }

    public static function permanentError(string $error): self
    {
        return new self(false, false, $error);
    }

    public static function temporaryError(string $error): self
    {
        return new self(false, true, $error);
    }
}
