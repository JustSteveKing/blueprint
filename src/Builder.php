<?php

declare(strict_types=1);

namespace Blueprint;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class Builder
{
    public function execute(
        Blueprint $blueprint,
        Filesystem $filesystem,
        string $draft,
        string $only = '',
        string $skip = '',
        bool $overwriteMigrations = false
    ): array|Collection {
        $cache = [];

        if ($filesystem->exists('.blueprint')) {
            $cache = $blueprint->parse(
                content: $filesystem->get('.blueprint'),
            );
        }

        $contents = $filesystem->get(
            path: $draft,
        );

        $tokens = $blueprint->parse(
            content: $contents,
            strip_dashes: preg_match('/^\s+indexes:\R/m', $contents) !== 1,
        );
        $tokens['cache'] = $cache['models'] ?? [];

        $generated = $blueprint->generate(
            tree: $blueprint->analyze(
                tokens: $tokens,
            ),
            only: array_filter(explode(',', $only)),
            skip: array_filter(explode(',', $skip)),
            overwriteMigrations: $overwriteMigrations,
        );

        $models = array_merge($tokens['cache'], $tokens['models'] ?? []);

        $filesystem->put(
            path: '.blueprint',
            contents: $blueprint->dump(
                generated: $generated + ($models ? ['models' => $models] : []),
            ),
        );

        return $generated;
    }
}
