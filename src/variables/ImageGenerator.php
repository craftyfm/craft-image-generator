<?php

namespace craftyfm\imagegenerator\variables;

use craft\base\Element;
use craft\errors\VolumeException;
use craftyfm\imagegenerator\models\Image;
use craftyfm\imagegenerator\Plugin;
use RuntimeException;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\ErrorException;
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
        $imageService = Plugin::getInstance()->imageService;
        $generatedImage = $imageService->getImageByTypeHandle($typeHandle, $element->id);

        if (!$generatedImage) {
            $type = Plugin::getInstance()->typeService->getTypeByHandle($typeHandle);
            if(!$type) {
                throw new RuntimeException("Image Generator: Type not found");
            }
            $generatedImage = new Image([
                'elementId' => $element->id,
                'typeId' => $type->id,
            ]);
            if(!$imageService->saveGeneratedImage($generatedImage)) {
                throw new RuntimeException("Failed to create generated image");
            }
            return $imageService->getGenerateUrl($generatedImage->id);
        }

        return $generatedImage->getUrl();
    }
}