<?php

namespace App\Entities;

class Label
{
    private $id;
    private $name;
    private $number;

    public function __construct($id, $name, $number)
    {
        $this->id = $id;
        $this->name = $name;
        $this->number = $number;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setNumber($number)
    {
        $this->number = $number;
    }
}
