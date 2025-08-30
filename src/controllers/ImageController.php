<?php

namespace craftyfm\imagegenerator\controllers;

use Craft;
use craft\web\Controller;
use craftyfm\imagegenerator\Plugin;
use Throwable;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ImageController extends Controller
{
    protected int|bool|array $allowAnonymous = ['generate'];


    public function actionIndex(string $type = null): Response
    {
        $types = Plugin::getInstance()->typeService->getAllTypes();
        $currentTypeId = null;

        foreach ($types as $t) {
            if ($t->handle === $type) {
                $currentTypeId = $t->id;
                break;
            }
        }
        return $this->renderTemplate('image-generator/images/index', compact('types',  'currentTypeId'));
    }

    public function actionTableData()
    {
        $page = (int)$this->request->getParam('page', 1);
        $limit = (int)$this->request->getParam('per_page', 25);
        $type =  $this->request->getParam('type') ? (int) $this->request->getParam('type') : null;
        [$pagination, $tableData] = Plugin::getInstance()->imageService->getTableData($page, $limit, $type);

        return $this->asSuccess(data: [
            'pagination' => $pagination,
            'data' => $tableData,
        ]);
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
        } catch (\Exception|Throwable $e) {
           Craft::error("Failed to generate image " . $e->getMessage(), __METHOD__);
           throw new BadRequestHttpException($e->getMessage());
        }


        $url = $generatedImage->getUrl();
        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['url' => $url]);
        }
        return $this->redirect($url);
    }

    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function actionDelete(): Response
    {
        $id = $this->request->getRequiredBodyParam('id');
        if (Plugin::getInstance()->imageService->deleteGeneratedImage($id)) {
            return $this->asSuccess('Status deleted successfully.');
        }
        return $this->asFailure('Failed to delete image.');
    }
}