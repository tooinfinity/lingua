<?php

declare(strict_types=1);

namespace TooInfinity\Lingua\Exceptions;

use InvalidArgumentException;

final class UnsupportedLocaleException extends InvalidArgumentException
{
    /**
     * @param  array<string>  $supportedLocales
     */
    public function __construct(
        private readonly string $locale,
        private readonly array $supportedLocales,
    ) {
        parent::__construct(
            sprintf(
                'Locale "%s" is not supported. Supported locales: %s',
                $locale,
                implode(', ', $supportedLocales)
            )
        );
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return array<string>
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }
}
