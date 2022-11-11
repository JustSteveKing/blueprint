<?php

declare(strict_types=1);

namespace Blueprint;

use Blueprint\Models\Controller;
use Blueprint\Models\Model;
use Illuminate\Support\Str;

class Tree
{
    public function __construct(
        private array $tree,
        private array $models = [],
    ) {
        $this->registerModels();
    }

    private function registerModels(): void
    {
        $this->models = [
            ...$this->tree['cache'] ?? [],
            ...$this->tree['models'] ?? []
        ];
    }

    /**
     * @return array<int,Controller>
     */
    public function controllers(): array
    {
        return $this->tree['controllers'];
    }

    /**
     * @return array<int,Model>
     */
    public function models(): array
    {
        return $this->tree['models'];
    }

    /**
     * @return array<int,string>
     */
    public function seeders(): array
    {
        return $this->tree['seeders'];
    }

    public function modelForContext(string $context): null|Model
    {
        if (isset($this->models[Str::studly($context)])) {
            return $this->models[Str::studly($context)];
        }

        if (isset($this->models[Str::studly(Str::plural($context))])) {
            return $this->models[Str::studly(Str::plural($context))];
        }

        $matches = array_filter(array_keys($this->models), fn ($key) => Str::endsWith(Str::afterLast(Str::afterLast($key, '\\'), '/'), [Str::studly($context), Str::studly(Str::plural($context))]));

        if (count($matches) === 1) {
            return $this->models[current($matches)];
        }

        return null;
    }

    public function fqcnForContext(string $context): string
    {
        if (isset($this->models[$context])) {
            return $this->models[$context]->fullyQualifiedClassName();
        }

        $matches = array_filter(
            array_keys($this->models),
            static fn ($key): bool => Str::endsWith(
                $key,
                '\\' . Str::studly($context)
            )
        );

        if (count($matches) === 1) {
            return $this->models[current($matches)]->fullyQualifiedClassName();
        }

        $fqn = config('blueprint.namespace');

        if (config('blueprint.models_namespace')) {
            $fqn .= '\\' . config('blueprint.models_namespace');
        }

        return "$fqn\\$context";
    }

    public function toArray(): array
    {
        return $this->tree;
    }
}
