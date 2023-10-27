<?php

namespace attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class authenticate {
    public string $description = '';

    public function __construct(?string $description = '') {
        $this->description ??= $description;
    }

    public function get_description(): string {
        return $this->description;
    }
}