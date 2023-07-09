<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class NoTryOut
{
    public function __construct(
        public ?bool $tryOut = false,
    )
    {
    }

    public function toArray()
    {
        return ["tryOut" => $this->tryOut];
    }
}
