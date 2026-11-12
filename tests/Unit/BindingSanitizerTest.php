<?php

declare(strict_types=1);

use Andriichuk\DbQuerySniffer\BindingSanitizer;

it('returns scalar bindings unchanged', function () {
    $sanitizer = new BindingSanitizer;

    expect($sanitizer->sanitize([1, true, null, 3.14]))
        ->toBe([1, true, null, 3.14]);
});

it('leaves normal strings unchanged', function () {
    $sanitizer = new BindingSanitizer;

    expect($sanitizer->sanitize(['hello']))
        ->toBe(['hello']);
});

it('encodes binary strings as base64 by default', function () {
    $sanitizer = new BindingSanitizer;
    $binary = "a\0b";

    expect($sanitizer->sanitize([$binary]))
        ->toBe(['[binary:3 bytes;base64='.base64_encode($binary).']']);
});

it('can omit base64 for binary strings', function () {
    $sanitizer = new BindingSanitizer(binaryAsBase64: false);
    $binary = "a\0b";

    expect($sanitizer->sanitize([$binary]))
        ->toBe(['[binary:3 bytes]']);
});

it('truncates long strings when max length is set', function () {
    $sanitizer = new BindingSanitizer(maxStringLength: 5);

    expect($sanitizer->sanitize(['abcdefgh']))
        ->toBe(['abcde…']);
});

it('sanitizes nested arrays recursively', function () {
    $sanitizer = new BindingSanitizer(binaryAsBase64: false);
    $binary = "x\0y";

    expect($sanitizer->sanitize([['nested' => $binary]]))
        ->toBe([['nested' => '[binary:3 bytes]']]);
});

it('handles invalid UTF-8 strings without NUL bytes as binary', function () {
    $binary = "\xff\xfe";
    $result = (new BindingSanitizer)->sanitize(['value' => $binary]);

    expect($result['value'])->toBe('[binary:2 bytes;base64='.base64_encode($binary).']')
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->toBeString();
});

it('preserves multibyte characters when truncating', function () {
    expect((new BindingSanitizer(maxStringLength: 2))->sanitize(['Привіт']))
        ->toBe(['Пр…']);
});

it('supports a zero string limit and preserves associative keys', function () {
    expect((new BindingSanitizer(maxStringLength: 0))->sanitize(['key' => 'text', 'empty' => '']))
        ->toBe(['key' => '…', 'empty' => '']);
});

it('rejects negative string limits', function () {
    expect(fn () => new BindingSanitizer(maxStringLength: -1))
        ->toThrow(InvalidArgumentException::class);
});
