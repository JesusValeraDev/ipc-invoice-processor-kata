<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\Address;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function test_formatted_output(): void
    {
        $address = new Address('Prinsengracht 123', 'Amsterdam', '1015 DT');

        $this->assertEquals("Prinsengracht 123\n1015 DT Amsterdam\nNL", $address->formatted());
    }

    public function test_default_country_is_nl(): void
    {
        $address = new Address('Street', 'City', '12345');

        $this->assertEquals('NL', $address->country);
    }

    public function test_custom_country(): void
    {
        $address = new Address('Street', 'City', '12345', 'DE');

        $this->assertEquals('DE', $address->country);
    }
}
