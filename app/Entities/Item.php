<?php
namespace App\Entities;

use App\Entities\PosterImage;
use App\Entities\JacketImage;
use App\Entities\Label;
use App\Entities\SaleType;
use App\Entities\Category;
use App\Entities\Series;
use App\Entities\Performer;
use App\Entities\Review;
use App\Entitties\Director;

class Item
{
    private $productId;
    private $title;
    private $originalTitle;
    private $caption;
    private $makerName;
    private $url;
    private $affiliateUrl;
    private $openDate;
    private $releaseDate;
    private $itemNo;
    private $price;
    private $volume;
    private PosterImage $posterImage;
    private JacketImage $jacketImage;
    private SampleMovie $sampleMovie;
    private array $thumbnail;
    private Label $label;
    private array $category;
    private Series $series;
    private array $performer;
    private array $director;
    private array $saleType;
    private $ranking;
    private Review $review;
    private $myList;

    public function __construct($productId, $title, $originalTitle, $caption, $makerName, $url, $affiliateUrl, $openDate, $releaseDate, $itemNo, $price, $volume, $posterImage, $jacketImage, $sampleMovie, $thumbnail, $label, $performer, $director, $saleType, $category, $series, $ranking, $review, $myList)
    {
        $this->productId = $productId;
        $this->title = $title;
        $this->originalTitle = $originalTitle;
        $this->caption = $caption;
        $this->makerName = $makerName;
        $this->url = $url;
        $this->affiliateUrl = $affiliateUrl;
        $this->openDate = $openDate;
        $this->releaseDate = $releaseDate;
        $this->itemNo = $itemNo;
        $this->price = $price;
        $this->volume = $volume;
        $this->posterImage = $posterImage;
        $this->jacketImage = $jacketImage;
        $this->sampleMovie = $sampleMovie;
        $this->thumbnail = $thumbnail;
        $this->label = $label;
        $this->performer = $performer;
        $this->director = $director;
        $this->saleType = $saleType;
        $this->category = $category;
        $this->series = $series;
        $this->ranking = $ranking;
        $this->review = 	$review;
        $this->myList = 	$myList;
    }

    public function getProductId()
    {
        return $this->productId;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getOriginalTitle()
    {
        return $this->originalTitle;
    }

    public function getCaption()
    {
        return $this->caption;
    }

    public function getMakerName()
    {
        return $this->makerName;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getAffiliateUrl()
    {
        return $this->affiliateUrl;
    }

    public function getOpenDate()
    {
        return $this->openDate;
    }

    public function getReleaseDate()
    {
        return $this->releaseDate;
    }

    public function getItemNo()
    {
        return $this->itemNo;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getVolume()
    {
        return $this->volume;
    }

    public function getPosterImage()
    {
        return $this->posterImage;
    }

    public function getJacketImage()
    {
        return $this->jacketImage;
    }
    public function getSampleMovie()
    {
        return $this->sampleMovie;
    }

    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getPerformer()
    {
        return $this->performer;
    }

    public function getDirector()
    {
        return $this->director;
    }

    public function getSaleType()
    {
        return $this->saleType;
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function getSeries()
    {
        return $this->series;
    }

    public function getRanking()
    {
        return $this->ranking;
    }

    public function getReview()
    {
        return $this->review;
    }

    public function getMyList()
    {
        return $this->myList;
    }

    public function setProductId($productId)
    {
        $this->productId = $productId;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setOriginalTitle($originalTitle)
    {
        $this->originalTitle = $originalTitle;
    }

    public function setCaption($caption)
    {
        $this->caption = $caption;
    }

    public function setMakerName($makerName)
    {
        $this->makerName = $makerName;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setAffiliateUrl($affiliateUrl)
    {
        $this->affiliateUrl = $affiliateUrl;
    }

    public function setOpenDate($openDate)
    {
        $this->openDate = $openDate;
    }

    public function setReleaseDate($releaseDate)
    {
        $this->releaseDate = $releaseDate;
    }

    public function setItemNo($itemNo)
    {
        $this->itemNo = $itemNo;
    }

    public function setPrice($price)
    {
        $this->price = $price;
    }

    public function setVolume($volume)
    {
        $this->volume = $volume;
    }

    public function setPosterImage(PosterImage $posterImage)
    {
        $this->posterImage = $posterImage;
    }

    public function setJacketImage(JacketImage $jacketImage)
    {
        $this->jacketImage = $jacketImage;
    }

    public function setSampleMovie(SampleMovie $sampleMovie)
    {
        $this->sampleMovie = $sampleMovie;
    }

    public function setThumbnail(array $thumbnail)
    {
        $this->thumbnail = $thumbnail;
    }

    public function setLabel(Label $label)
    {
        $this->label = $label;
    }

    public function setPerformer(array $performer)
    {
        $this->performer = $performer;
    }

    public function setDirector(array $director)
    {
        $this->director = $director;
    }

    public function setSaleType(array $saleType)
    {
        $this->saleType = $saleType;
    }

    public function setCategory(Category $category)
    {
        $this->category = $category;
    }

    public function setSeries(Series $series)
    {
        $this->series = $series;
    }

    public function setRanking($ranking)
    {
        $this->ranking = $ranking;
    }

    public function setReview(Review $review)
    {
        $this->review = $review;
    }

    public function setMyList($myList)
    {
        $this->myList = $myList;
    }
}
