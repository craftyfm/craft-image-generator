<?php

namespace craftyfm\imagegenerator\models;

use craft\base\Model;
use craft\helpers\UrlHelper;
use DateTime;

class GeneratedImageType extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $handle = '';
    public ?int $width = null;
    public ?int $height = null;
    public string $format = 'jpg';
    public int $quality = 80;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public string $uid;

    public function rules(): array
    {
        return [
            [['name', 'handle', 'format'], 'required'],
            [['width', 'height', 'quality'], 'integer'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['format'], 'string', 'max' => 10],
        ];
    }

    public function getCpEditUrl(): string
    {
        if (!$this->id) {
            return UrlHelper::cpUrl('image-generator/types/new');
        }
        return UrlHelper::cpUrl('image-generator/types/' . $this->id);

    }
    public function getFormatOptions(): array
    {
        return [
            'jpg' => 'JPG',
            'jpeg' => 'JPEG',
            'png' => 'PNG',
            'webp' => 'WEBP',
        ];

    }
}
