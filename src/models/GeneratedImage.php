<?php

namespace craftyfm\imagegenerator\models;

use craft\base\Model;
use craft\elements\Asset;
use DateTime;
use yii\base\InvalidConfigException;

class GeneratedImage extends Model
{
    public ?int $id = null;
    public ?int $assetId = null;
    public ?int $elementId = null;
    public ?int $typeId = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    private ?Asset $_asset;
    public function getAsset(): ?Asset
    {
        if (isset($this->_asset)) {
            return $this->_asset;
        }
        if (!isset($this->assetId)) {
            return null;
        }

        $this->_asset = Asset::find()->id($this->assetId)->one();
        return $this->_asset;
    }

    public function setAsset(Asset $asset): void
    {
        $this->_asset = $asset;
        $this->assetId = $asset->id;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getUrl(): string
    {
        $asset = $this->getAsset();
        if (!$asset) {
            return '';
        }

        return $asset->getUrl();
    }
    public function rules(): array
    {
        return [
            [['elementId', 'typeId'], 'required'],
            [['assetId', 'elementId', 'typeId'], 'integer'],
        ];
    }
}
