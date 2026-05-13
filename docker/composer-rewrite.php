<?php

/**
 * Rewrites composer.json's `repositories` section for the demo-image
 * build. Replaces the dev `path` repo at `../imanager` (which doesn't
 * exist inside the container) with a VCS repo pointing at the public
 * iManager GitHub repository.
 *
 * Run by the Dockerfile right before `composer update`.
 */

declare(strict_types=1);

$file = 'composer.json';
$raw  = file_get_contents($file);
if ($raw === false) {
    fwrite(\STDERR, "[composer-rewrite] cannot read {$file}\n");
    exit(1);
}

$c = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
$c['repositories'] = [
    ['type' => 'vcs', 'url' => 'https://github.com/bigin/imanager'],
];

$out = json_encode($c, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . \PHP_EOL;
file_put_contents($file, $out);
fwrite(\STDOUT, "[composer-rewrite] swapped path repo for VCS repo.\n");
