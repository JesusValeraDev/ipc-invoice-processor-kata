<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class Customer
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?Address $address = null,
    ) {}
}
