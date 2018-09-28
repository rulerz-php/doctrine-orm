<?php

namespace Entity;

/**
 * @Embeddable
 */
class Address
{
    /**
     * @Column(type = "string")
     */
    public $street;

    /**
     * @Column(type = "string")
     */
    public $postalCode;

    /**
     * @Column(type = "string")
     */
    public $city;

    /**
     * @Column(type = "string")
     */
    public $country;

    public function __construct(string $street, string $postalCode, string $city, string $country)
    {
        $this->street = $street;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->country = $country;
    }
}
