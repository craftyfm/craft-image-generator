<?php

namespace craftyfm\imagegenerator\variables;

use craft\base\Element;
use craft\errors\VolumeException;
use craftyfm\imagegenerator\Plugin;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\ErrorException;
use yii\db\Exception;

class ImageGenerator
{

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws Exception
     * @throws SyntaxError
     * @throws VolumeException
     * @throws \yii\base\Exception
     * @throws ErrorException
     * @throws Throwable
     */
    public function getUrl(string $typeHandle, Element $element): string
    {
        $imageService = Plugin::getInstance()->imageService;
        $generatedImage = $imageService->getImageByTypeHandle($typeHandle, $element->id);

        if (!$generatedImage || $generatedImage->assetId === null || !$generatedImage->getUrl()) {
            $type = Plugin::getInstance()->typeService->getTypeByHandle($typeHandle);
            $generatedImage = $imageService->generateImage($element, $type);
        }

        return $generatedImage->getUrl();
    }
}