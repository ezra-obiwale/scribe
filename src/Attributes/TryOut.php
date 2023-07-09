<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class TryOut
{
    public function __construct(
        public ?bool $tryOut = true,
    )
    {
    }

    public function toArray()
    {
        return ["tryOut" => $this->tryOut];
    }
}
