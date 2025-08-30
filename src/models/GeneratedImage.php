<?php

namespace craftyfm\imagegenerator\models;

use craft\base\Model;
use craft\elements\Asset;
use craft\helpers\UrlHelper;
use craftyfm\imagegenerator\Plugin;
use DateTime;
use RuntimeException;
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
    private ?GeneratedImageType $_type;
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
        if (!$this->id || !$this->getAsset()) {
            throw new RuntimeException("Generated Image doesn't have id or asset.");
        }
        return $this->getAsset()->getUrl();
    }

    public function getType(): ?GeneratedImageType
    {
        if (isset($this->_type)) {
            return $this->_type;
        }
        if (!isset($this->typeId)) {
            return null;
        }

        $this->_type = Plugin::getInstance()->typeService->getTypeById($this->typeId);
        return $this->_type;
    }

    public function rules(): array
    {
        return [
            [['elementId', 'typeId', 'assetId'], 'required'],
            [['assetId', 'elementId', 'typeId'], 'integer'],
        ];
    }
}
