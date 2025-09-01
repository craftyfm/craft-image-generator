<?php

namespace craftyfm\imagegenerator\variables;

use craft\base\Element;
use craft\helpers\ElementHelper;
use craftyfm\imagegenerator\models\Image;
use craftyfm\imagegenerator\Plugin;
use RuntimeException;
use yii\base\InvalidConfigException;
use yii\db\Exception;

class ImageGenerator
{

    /**
     * @param string $typeHandle
     * @param Element $element
     * @return string
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function getUrl(string $typeHandle, Element $element): string
    {
        if (ElementHelper::isDraftOrRevision($element)) {
            $mainElement = $element->getCanonical();
        } else {
            $mainElement = $element;
        }

        $imageService = Plugin::getInstance()->imageService;
        $generatedImage = $imageService->getImageByTypeHandle($typeHandle, $mainElement->id);

        if (!$generatedImage) {
            $type = Plugin::getInstance()->typeService->getTypeByHandle($typeHandle);
            if(!$type) {
                throw new RuntimeException("Image Generator: Type not found");
            }
            $generatedImage = new Image([
                'elementId' => $mainElement->id,
                'typeId' => $type->id,
            ]);
            if(!$imageService->saveGeneratedImage($generatedImage)) {
                throw new RuntimeException("Failed to create generated image");
            }
        }

        if (!$generatedImage->assetId) {
            return $imageService->getGenerateUrl($generatedImage->id);
        }
        return $generatedImage->getUrl();
    }
}