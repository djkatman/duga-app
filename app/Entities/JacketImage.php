<?php

namespace App\Entities;

class JacketImage
{
    private $small;
    private $midium;
    private $large;

    public function __construct($small, $midium, $large)
    {
        $this->small = $small;
        $this->midium = $midium;
        $this->large = $large;
    }

    public function getSmall()
    {
        return $this->small;
    }

    public function getMidium()
    {
        return $this->midium;
    }

    public function getLarge()
    {
        return $this->large;
    }

    public function setSmall($small)
    {
        $this->small = $small;
    }

    public function setMidium($midium)
    {
        $this->midium = $midium;
    }

    public function setLarge($large)
    {
        $this->large = $large;
    }
}
