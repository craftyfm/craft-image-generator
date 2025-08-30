<?php

namespace craftyfm\imagegenerator\controllers;

use Craft;
use craft\web\Controller;
use craftyfm\imagegenerator\Plugin;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ImageController extends Controller
{
    protected int|bool|array $allowAnonymous = ['generate'];


    public function actionIndex(): Response
    {

        return $this->renderTemplate('image-generator/images/index');
    }

    public function actionTableData()
    {
        $page = (int)$this->request->getParam('page', 1);
        $limit = (int)$this->request->getParam('per_page', 100);
    }

    /**
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     * @throws InvalidConfigException
     */
    public function actionGenerate(int $id): Response
    {

        $generatedImage = Plugin::getInstance()->imageService->getImageById($id);

        if (!$generatedImage) {
            throw new NotFoundHttpException('Image not found');
        }

        try {
            if (!$generatedImage->getAsset()) {
                Plugin::getInstance()->imageService->generateImage($generatedImage);
            }
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