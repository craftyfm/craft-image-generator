<?php

namespace craftyfm\imagegenerator\models;

use craft\base\Model;
use craft\helpers\UrlHelper;
use craftyfm\imagegenerator\records\ImageTypeRecord;
use DateTime;

class ImageType extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $handle = '';
    public string $template = '';
    public ?int $width = null;
    public ?int $height = null;
    public string $format = 'jpg';
    public int $quality = 80;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    public function rules(): array
    {
        return [
            [['name', 'handle', 'format', 'template'], 'required'],
            [['handle'], 'unique', 'targetClass' => ImageTypeRecord::class,
                'filter' => function ($query) {
                    if ($this->id) {
                        $query->andWhere(['<>', 'id', $this->id]);
                    }
                },
                'message' => 'This handle has already been taken.'],
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
