<?php

declare(strict_types=1);

namespace Andriichuk\DbQuerySniffer;

final class BindingSanitizer
{
    public function __construct(
        private readonly ?int $maxStringLength = null,
        private readonly bool $binaryAsBase64 = true,
    ) {}

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

        if (str_contains($binding, "\0")) {
            $length = strlen($binding);

            if (! $this->binaryAsBase64) {
                return '[binary:'.$length.' bytes]';
            }

            return '[binary:'.$length.' bytes;base64='.base64_encode($binding).']';
        }

        if ($this->maxStringLength !== null && mb_strlen($binding) > $this->maxStringLength) {
            return mb_substr($binding, 0, $this->maxStringLength).'…';
        }

        return $binding;
    }
}
