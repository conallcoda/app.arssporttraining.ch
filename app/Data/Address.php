<?php

namespace App\Data;

class Address extends AbstractData
{
    public function __construct(
        public ?string $street,
        public ?string $city,
        public ?string $state,
        public ?string $postcode,
        public ?string $country,
    ) {}
}
