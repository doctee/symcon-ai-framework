<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$fixtures = $root . '/deployments/symcon/windows/adapters/schema-probe/';
$source = (string) file_get_contents($root . '/case-studies/media-carousel/distribution/MediaCarousel/module.php');
preg_match_all('/\$this->RegisterProperty(?:String|Integer|Boolean)\([^;]+;/', $source, $current);
if (count($current[0]) !== 17) {
    throw new RuntimeException('Runtime registration changed: review schema qualification.');
}
foreach (['legacy', 'candidate'] as $version) {
    $fixture = (string) file_get_contents($fixtures . $version . '.php');
    preg_match_all('/\$this->RegisterProperty(?:String|Integer|Boolean)\([^;]+;/', $fixture, $actual);
    $expected = array_values(array_filter($current[0], static fn(string $line): bool =>
        $version === 'candidate' || !str_contains($line, "'ShowFitToggle'")));
    if ($actual[0] !== $expected) {
        throw new RuntimeException('Inert fixture differs from runtime property registration.');
    }
    foreach (token_get_all($fixture) as $token) {
        if (
            is_array($token) && $token[0] === T_STRING && !in_array($token[1], [
            'strict_types', 'SAEFMediaCarouselSchemaProbe', 'IPSModuleStrict', 'Create', 'ApplyChanges',
            'void', 'parent', 'RegisterPropertyString', 'RegisterPropertyInteger', 'RegisterPropertyBoolean',
            'true', 'false', 'self', 'SOURCE_LIST',
            ], true)
        ) {
            throw new RuntimeException('Unexpected executable token in inert schema fixture: ' . $token[1]);
        }
    }
}
echo "MediaCarousel inert schema fixtures match runtime registrations.\n";
