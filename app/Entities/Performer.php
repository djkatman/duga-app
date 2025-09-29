<?php

namespace App\Entities;

class Performer
{
    private $id;
    private $name;
    private $kana;

    public function __construct($id, $name, $kana)
    {
        $this->id = $id;
        $this->name = $name;
        $this->kana = $kana;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getKana()
    {
        return $this->kana;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setKana($kana)
    {
        $this->kana = $kana;
    }
}
