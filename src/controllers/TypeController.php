<?php

namespace craftyfm\imagegenerator\controllers;

use Craft;
use craft\errors\BusyResourceException;
use craft\errors\MissingComponentException;
use craft\errors\StaleResourceException;
use craft\web\Controller;
use craftyfm\imagegenerator\models\GeneratedImageType;
use craftyfm\imagegenerator\Plugin;
use craftyfm\imagegenerator\services\TypeService;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class TypeController extends Controller
{
    public function actionIndex(): Response
    {
        $types = Plugin::getInstance()->typeService->getAllTypes();

        $tableData = [];
        foreach ($types as $type) {
            $tableData[] = [
                'id' => $type->id,
                'title' => $type->name,
                'handle' => $type->handle,
                'width' => $type->width,
                'height' => $type->height,
                'format' => $type->format,
                'quality' => $type->quality,
                'url' => $type->getCpEditUrl(),
            ];
        }

        return $this->renderTemplate('image-generator/types/index', [
            'tableData' => $tableData,
        ]);
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionEdit(int $id = null, GeneratedImageType $type = null): Response
    {

        if (!$type) {
            $type = $id !== null ? Plugin::getInstance()->typeService->getTypeById($id) : new GeneratedImageType();
        }

        if (!$type) {
            throw new NotFoundHttpException('Type not found');
        }

        return $this->renderTemplate('image-generator/types/edit', [
            'type' => $type,
        ]);
    }

    /**
     * @throws NotSupportedException
     * @throws MissingComponentException
     * @throws InvalidConfigException
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     * @throws ServerErrorHttpException
     * @throws StaleResourceException
     * @throws BusyResourceException
     * @throws ErrorException
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $type = new GeneratedImageType();

        // Populate the model
        $type->id = $request->getBodyParam('id') ?? null;
        $type->name = $request->getBodyParam('name');
        $type->handle = $request->getBodyParam('handle');
        $type->width = $request->getBodyParam('width');
        $type->height = $request->getBodyParam('height');
        $type->format = $request->getBodyParam('format', 'jpg');
        $type->quality = (int)$request->getBodyParam('quality', 80);
        $type->template = $request->getBodyParam('template');


        // Save the type
        if (!Plugin::getInstance()->typeService->saveType($type)) {
            return $this->asFailure("Failed to save type.",
                ['errors' => $type->getErrors()], ['type' => $type]
            );
        }

        return $this->asSuccess("Type saved.", ['type' => $type]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        if (Plugin::getInstance()->typeService->deleteTypeById($id)) {
            return $this->asSuccess('Status deleted successfully.');
        }

        return $this->asFailure("Failed to delete status.");
    }
}