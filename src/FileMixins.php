<?php

declare(strict_types=1);

namespace Blueprint;

use Closure;
use Illuminate\Filesystem\Filesystem;

/**
 * @mixin Filesystem
 */
class FileMixins
{
    /**
     * @var array<string,string>
     */
    private array $stubs = [];

    public function stub(): Closure
    {
        return function ($path): string {
            if (!isset($this->stubs[$path])) {
                $stubPath = file_exists($customPath = CUSTOM_STUBS_PATH . '/' . $path)
                          ? $customPath
                          : STUBS_PATH . '/' . $path;

                $this->stubs[$path] = $this->get($stubPath);
            }

            return $this->stubs[$path];
        };
    }
}
