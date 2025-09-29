<?php
// app/Utils/ConvertObject.php

namespace App\Utils;

use App\Entities\Category;
use App\Entities\Series;
use App\Entities\Performer;
use App\Entities\Label;
use App\Entities\SaleType;
use App\Entities\PosterImage;
use App\Entities\JacketImage;
use App\Entities\SampleMovie;
use App\Entities\Director;
use App\Entities\Review;
use App\Entities\Item;

class ConvertObject
{
    public static function arrayToObject($items): array {
        $itemsArray = array();
        foreach ($items as $item) {
            foreach($item as $row) {
                // メイン画像
                $posterImage = new PosterImage('', '', '');
                if(isset($row['posterimage'])) {
                    $posterImage->setSmall($row['posterimage'][0]['small']);
                    $posterImage->setMidium($row['posterimage'][1]['midium']);
                    $posterImage->setLarge($row['posterimage'][2]['large']);
                }
                // ジャケット画像
                $jacketImage = new JacketImage('', '', '');
                if(isset($row['jacketimage'])) {
                    $jacketImage->setSmall($row['jacketimage'][0]['small']);
                    $jacketImage->setMidium($row['jacketimage'][1]['midium']);
                    $jacketImage->setLarge($row['jacketimage'][2]['large']);
                }
                // サンプル画像
                $thumbNailArray = array();
                if(isset($row['thumbnail'])) {
                    foreach($row['thumbnail'] as $thumb) {
                        $thumbNailArray[] .= $thumb['image'];
                    }
                }
                // サンプル動画
                $sampleMovie  = new SampleMovie('', '');
                if(isset($row['samplemovie'])) {
                    $sampleMovie->setMovie($row['samplemovie'][0]['midium']['movie']);
                    $sampleMovie->setCapture($row['samplemovie'][0]['midium']['capture']);
                }
                // レーベル
                $label = new Label('', '', '');
                if(isset($row['label'])) {
                    foreach($row['label'] as $labelRow) {
                       $label->setId($labelRow['id']);
                       $label->setName($labelRow['name']);
                       $label->setNumber($labelRow['number']);
                    }
                }
                // カテゴリ
                $categoryArray = array();
                if(isset($row['category'])) {
                    foreach($row['category'] as $categoryRow) {
                        $category = new Category('', '');
                        $category->setId($categoryRow['data']['id']);
                        $category->setName($categoryRow['data']['name']);
                        $categoryArray[] = $category;
                    }
                }
                // シリーズ
                $series = new Series('', '');
                if(isset($row['series'])) {
                    foreach($row['series'] as $seriesRow) {
                        $series->setId($seriesRow['id']);
                        $series->setName($seriesRow['name']);
                    }
                }
                // 出演者情報
                $performerArray = array();
                if(isset($row['performer'])) {
                    foreach($row['performer'] as $performerRow) {
                        $performer = new Performer('', '', '');
                        $performer->setId($performerRow['data']['id']);
                        $performer->setName($performerRow['data']['name']);
                        $performer->setKana($performerRow['data']['kana']);
                        $performerArray[] = $performer;
                    }
                }
                // 監督情報
                $directorArray = array();
                if(isset($row['director'])) {
                    foreach($row['director'] as $directorRow) {
                        $director = new Director('', '');
                        $director->setId($directorRow['data']['id']);
                        $director->setName($directorRow['data']['name']);
                        $directorArray[] = $director;
                    }
                }
                // 販売形態
                $saleTypeArray = array();
                if(isset($row['saletype'])) {
                    foreach($row['saletype'] as $saleTypeRow) {
                        $saleType = new SaleType('', '');
                        $saleType->setType($saleTypeRow['data']['type']);
                        $saleType->setPrice($saleTypeRow['data']['price']);
                        $saleTypeArray[] = $saleType;
                    }
                }
                // レビュー
                $review = new Review('','');
                if(isset($row['review'])) {
                    foreach($row['review'] as $reviewRow) {
                        $review->setRating($reviewRow['rating']);
                        $review->setReviewer($reviewRow['reviewer']);
                    }
                }

                // ランキング
                $ranking = $row['ranking'][0]['total'] ?? '';
                $ranking = str_replace(',', '', $ranking);

                // アイテム情報
                $itemObj = new Item(
                    $row['productid'] ?? '',
                    $row['title'] ?? '',
                    $row['originaltitle'] ?? '',
                    $row['caption'] ?? '',
                    $row['makername'] ?? '',
                    $row['url'] ?? '',
                    $row['affiliateurl'] ?? '',
                    $row['opendate'] ?? '',
                    $row['releasedate'] ?? '',
                    $row['itemno'] ?? '',
                    $row['price'] ?? '',
                    $row['volume'] ?? '',
                    $posterImage,
                    $jacketImage,
                    $sampleMovie,
                    $thumbNailArray,
                    $label,
                    $performerArray,
                    $directorArray,
                    $saleTypeArray,
                    $categoryArray,
                    $series,
                    $ranking,
                    $review,
                    $row['mylist'][0]['total'] ?? ''
                );
                $itemsArray[] = $itemObj;
            }
        }
        return $itemsArray;
    }
}
