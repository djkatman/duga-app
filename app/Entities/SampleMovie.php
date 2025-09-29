<?php

namespace App\Entities;

class SampleMovie
{
    private $movie;
    private $capture;

    public function __construct($movie, $capture)
    {
        $this->movie = $movie;
        $this->capture = $capture;
    }

    public function getMovie()
    {
        return $this->movie;
    }

    public function getCapture()
    {
        return $this->capture;
    }

    public function setMovie($movie)
    {
        $this->movie = $movie;
    }

    public function setCapture($capture)
    {
        $this->capture = $capture;
    }
}
