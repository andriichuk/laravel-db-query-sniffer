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
