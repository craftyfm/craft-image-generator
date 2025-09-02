<?php

namespace craftyfm\imagegenerator\models;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\elements\Asset;
use craftyfm\imagegenerator\Plugin;
use DateTime;
use RuntimeException;
use yii\base\InvalidConfigException;

class Image extends Model
{
    public ?int $id = null;
    public ?int $assetId = null;
    public ?int $elementId = null;
    public ?int $typeId = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    private ?Asset $_asset;
    private ImageType $_type;
    private Element $_element;

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
        if (!$this->id) {
            throw new RuntimeException("Generated Image doesn't have id.");
        }

        if (!$this->getAsset()) {
            throw new RuntimeException("Image not yet generated.");
        }
        return $this->getAsset()->getUrl();
    }

    public function getType(): ?ImageType
    {
        if (isset($this->_type)) {
            return $this->_type;
        }
        if (!isset($this->typeId)) {
            throw new RuntimeException("Image doesn't have typeId.");
        }

        $this->_type = Plugin::getInstance()->typeService->getTypeById($this->typeId);
        return $this->_type;
    }

    public function getElement(): Element
    {
        if (isset($this->_element)) {
            return $this->_element;
        }
        if (!isset($this->elementId)) {
            throw new RuntimeException("Image doesn't have elementId.");
        }

        $this->_element = Craft::$app->elements->getElementById($this->elementId);

        return $this->_element;
    }

    public function setElement(Element $element): void
    {
        $this->_element = $element;
        $this->elementId = $element->id;
    }

    public function rules(): array
    {
        return [
            [['elementId', 'typeId'], 'required'],
            [['assetId', 'elementId', 'typeId'], 'integer'],
        ];
    }

    public function getCpUrl(): string
    {
        if (!$this->id) {
            throw new RuntimeException("Generated Image doesn't have id.");
        }
        return Plugin::getInstance()->imageService->getCpUrl($this->id);
    }
}
