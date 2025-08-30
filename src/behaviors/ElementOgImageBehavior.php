<?php

namespace craftyfm\imagegenerator\behaviors;

use craft\elements\Asset;
use craftyfm\imagegenerator\Plugin;
use yii\base\Behavior;

/**
 * Element  Image Behavior
 *
 * Adds  image functionality to elements
 */
class ElementImageBehavior extends Behavior
{
    private ?Asset $_Image = null;

    /**
     * Get the generated  image for this element
     */
    public function getGeneratedImage(): ?Asset
    {
        if ($this->_Image === null) {
            $this->_Image = Plugin::getInstance()->ImageService->getImageForElement($this->owner);
        }

        return $this->_Image;
    }

    /**
     * Generate  image for this element
     */
    public function generateImage(bool $force = false): ?Asset
    {
        // Remove existing if forcing regeneration
        if ($force && $this->getGeneratedImage()) {
            Plugin::getInstance()->ImageService->removeImageForElement($this->owner);
            $this->_Image = null;
        }

        $asset = Plugin::getInstance()->ImageService->generateImage($this->owner);
        if ($asset) {
            $this->_Image = $asset;
        }

        return $asset;
    }

    /**
     * Remove  image for this element
     */
    public function removeImage(): bool
    {
        $result = Plugin::getInstance()->imageService->removeImageForElement($this->owner);
        if ($result) {
            $this->_Image = null;
        }

        return $result;
    }

    /**
     * Check if element has an  image
     */
    public function hasImage(): bool
    {
        return $this->getGeneratedImage() !== null;
    }
}