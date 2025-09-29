<?php

namespace App\Entities;

class SaleType
{
    private $type;
    private $price;

    public function __construct($type, $price)
    {
        $this->type = $type;
        $this->price = $price;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setPrice($price)
    {
        $this->price = $price;
    }
}
