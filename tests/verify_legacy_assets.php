<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/resources/legacy';
$manifest = json_decode(file_get_contents($root . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$folders = [
    'background' => 'backgrounds',
    'face' => 'faces',
    'uniform' => 'uniforms',
    'hair' => 'hair',
    'amulet' => 'amulets',
];

foreach ($manifest['layers'] as $layer) {
    $path = sprintf('%s/layers/%s/%s', $root, $folders[$layer['category']], $layer['filename']);
    if (!is_file($path)) {
        throw new RuntimeException('Missing legacy layer: ' . $path);
    }

    $image = new Imagick($path);
    if ($image->getImageWidth() !== 1080 || $image->getImageHeight() !== 530) {
        throw new RuntimeException(sprintf(
            '%s is %dx%d; expected 1080x530.',
            $path,
            $image->getImageWidth(),
            $image->getImageHeight(),
        ));
    }
}

printf(
    "%d layers and %d award placements are valid.\n",
    count($manifest['layers']),
    count($manifest['award_categories']),
);
