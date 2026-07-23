<?php

declare(strict_types=1);

use App\Conversion\Converter;

require __DIR__ . '/../autoload.php';

$converter = new Converter();
$method = new ReflectionMethod(Converter::class, 'sanitizeHtmlForDocx');
$method->setAccessible(true);

$unsafeHtml = '<p>Candidate profile</p>'
    . '<img src="https://attacker.example/tracker.png">'
    . '<img src="../../private-file.png">'
    . '<iframe src="file:///etc/passwd"></iframe>'
    . '<object data="ftp://attacker.example/payload"></object>'
    . '<script src="//attacker.example/script.js"></script>'
    . '<img src="data:image/png;base64,AAAA">'
    . '<a href="https://example.com/profile">Public profile</a>';

$sanitized = $method->invoke($converter, $unsafeHtml);

if (!is_string($sanitized)) {
    throw new RuntimeException('The converter sanitizer did not return HTML.');
}

foreach (['attacker.example', '../../private-file.png', 'file:///etc/passwd'] as $blockedValue) {
    if (strpos($sanitized, $blockedValue) !== false) {
        throw new RuntimeException(sprintf('Unsafe resource remained in rendered HTML: %s', $blockedValue));
    }
}

if (strpos($sanitized, 'data:image/png;base64,AAAA') === false) {
    throw new RuntimeException('A self-contained image was removed by the sanitizer.');
}

if (strpos($sanitized, 'https://example.com/profile') === false) {
    throw new RuntimeException('A normal hyperlink was removed by the resource sanitizer.');
}

echo 'ConverterSecurityTest passed' . PHP_EOL;
