<?php
namespace App\Entities;


class Review
{
    private $rating;
    private $reviewer;

    public function __construct($rating, $reviewer)
    {
        $this->rating = $rating;
        $this->reviewer = $reviewer;
    }

    public function getRating()
    {
        return $this->rating;
    }

    public function getReviewer()
    {
        return $this->reviewer;
    }

    public function setRating($rating)
    {
        $this->rating = $rating;
    }

    public function setReviewer($reviewer)
    {
        $this->reviewer = $reviewer;
    }
}
