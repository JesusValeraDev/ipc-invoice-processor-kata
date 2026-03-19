<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class Address
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $zipCode,
        public readonly string $country = 'NL',
    ) {}

    public function formatted(): string
    {
        return "{$this->street}\n{$this->zipCode} {$this->city}\n{$this->country}";
    }
}
