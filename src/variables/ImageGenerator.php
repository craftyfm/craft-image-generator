<?php

namespace craftyfm\imagegenerator\variables;

use craft\base\Element;
use craft\errors\VolumeException;
use craftyfm\imagegenerator\models\GeneratedImage;
use craftyfm\imagegenerator\Plugin;
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
     */
    public function getUrl(string $typeHandle, Element $element): string
    {
        $imageService = Plugin::getInstance()->imageService;
        $generatedImage = $imageService->getImageByTypeHandle($typeHandle, $element->id);

        if (!$generatedImage) {
            $type = Plugin::getInstance()->typeService->getTypeByHandle($typeHandle);
            return $imageService->getGenerateUrl($element->id, $type->id);
        }

        return $generatedImage->getUrl();
    }
}