<?php

namespace MdServer\Service;

final readonly class RenderResult
{
    public function __construct(
        public string $html,
        public array $frontmatter,
    ) {}
}
