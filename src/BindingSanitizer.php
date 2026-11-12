<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer;

use InvalidArgumentException;

final class BindingSanitizer
{
    public function __construct(
        private readonly ?int $maxStringLength = null,
        private readonly bool $binaryAsBase64 = true,
    ) {
        if ($this->maxStringLength !== null && $this->maxStringLength < 0) {
            throw new InvalidArgumentException('The maximum binding string length must be zero or greater.');
        }
    }

    /**
     * @param  array<int|string, mixed>  $bindings
     * @return array<int|string, mixed>
     */
    public function sanitize(array $bindings): array
    {
        $sanitized = [];

        foreach ($bindings as $key => $binding) {
            $sanitized[$key] = $this->sanitizeValue($binding);
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $binding): mixed
    {
        if (is_array($binding)) {
            return $this->sanitize($binding);
        }

        if (! is_string($binding)) {
            return $binding;
        }

        if (str_contains($binding, "\0") || ! mb_check_encoding($binding, 'UTF-8')) {
            $length = strlen($binding);

            if (! $this->binaryAsBase64) {
                return '[binary:'.$length.' bytes]';
            }

            return '[binary:'.$length.' bytes;base64='.base64_encode($binding).']';
        }

        if ($this->maxStringLength !== null && mb_strlen($binding, 'UTF-8') > $this->maxStringLength) {
            return mb_substr($binding, 0, $this->maxStringLength, 'UTF-8').'…';
        }

        return $binding;
    }
}
