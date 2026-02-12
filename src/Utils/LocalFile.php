<?php
declare(strict_types=1);

namespace ZenithGram\ZenithGram\Utils;

class LocalFile
{
    public function __construct(
        private string $path,
    ) {}

    public function getPath(): string
    {
        return $this->path;
    }
}