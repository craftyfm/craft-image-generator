<?php

namespace craftyfm\imagegenerator\services;

use craft\base\Component;
use craftyfm\imagegenerator\models\GeneratedImage;
use craftyfm\imagegenerator\records\GeneratedImageRecord;

class ImageService extends Component
{

    public function getImageById(int $id): ?GeneratedImage
    {
        $record = GeneratedImageRecord::findOne(['id' => $id]);
        if (!$record) {
            return null;
        }
        return new GeneratedImage($record->toArray());
    }

}