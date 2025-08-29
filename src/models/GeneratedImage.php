<?php

namespace craftyfm\imagegenerator\models;

use craft\base\Model;
use DateTime;

class GeneratedImage extends Model
{
    public ?int $id = null;
    public int $assetId;
    public int $elementId;
    public int $typeId;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public string $uid;

    public function rules(): array
    {
        return [
            [['assetId', 'elementId', 'typeId'], 'required'],
            [['assetId', 'elementId', 'typeId'], 'integer'],
        ];
    }
}
