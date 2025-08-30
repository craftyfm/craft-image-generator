<?php

namespace craftyfm\imagegenerator\controllers;

use Craft;
use craft\base\Element;
use craft\errors\VolumeException;
use craft\web\Controller;
use craft\web\Response;
use craftyfm\imagegenerator\Plugin;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ImageController extends Controller
{
    protected int|bool|array $allowAnonymous = ['generate'];

    /**
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws InvalidConfigException
     */
    public function actionGenerate(int $elementId, int $typeId): \yii\web\Response
    {

        $element = Craft::$app->getElements()->getElementById($elementId);
        $type = Plugin::getInstance()->typeService->getTypeById($typeId);
        if (!$element || !$type) {
            throw new NotFoundHttpException();
        }
        try {
            $generatedImage = Plugin::getInstance()->imageService->generateImage($element, $type);

        } catch (\Exception|\Throwable $e) {
           Craft::error("Failed to generate image " . $e->getMessage(), __METHOD__);
           throw new BadRequestHttpException($e->getMessage());
        }

        $url = $generatedImage->getUrl();

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['url' => $url]);
        }
        return $this->redirect($url);
    }
}