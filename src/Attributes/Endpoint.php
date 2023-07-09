<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Endpoint
{
    public function __construct(
        public string  $title,
        public ?string $description = '',
        /** You can use the separate #[Authenticated] attribute, or pass authenticated: false to this. */
        public ?bool   $authenticated = null,
        /** You can use the separate #[TryOut] attribute, or pass tryOut: false to this. */
        public ?bool   $tryOut = null,
    )
    {
    }

    public function toArray()
    {
        $data = [
            "title" => $this->title,
            "description" => $this->description,
        ];

        if (!is_null($this->authenticated)) {
            $data["authenticated"] = $this->authenticated;
        }

        if (!is_null($this->tryOut)) {
            $data["tryOut"] = $this->tryOut;
        }

        return $data;
    }
}
