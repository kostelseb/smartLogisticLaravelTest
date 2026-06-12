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

    public static function delivered(): self
    {
        return new self(true, false);
    }

    public static function permanentFailure(string $error): self
    {
        return new self(false, false, $error);
    }

    public static function temporaryFailure(string $error): self
    {
        return new self(false, true, $error);
    }
}
