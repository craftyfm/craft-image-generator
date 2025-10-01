<?php

namespace craftyfm\imagegenerator\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Asset;
use craft\errors\VolumeException;
use craft\helpers\AdminTable;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\web\View;
use craftyfm\imagegenerator\jobs\DeleteImagesForElementJob;
use craftyfm\imagegenerator\models\Image;
use craftyfm\imagegenerator\models\ImageType;
use craftyfm\imagegenerator\Plugin;
use craftyfm\imagegenerator\records\ImageRecord;
use craftyfm\imagegenerator\records\ImageTypeRecord;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\ErrorException;
use yii\db\Exception;
use yii\db\StaleObjectException;

class ImageService extends Component
{

    private bool $_onDeletingAsset = false;

    public function getImages(array $params = []): array
    {
        $query = ImageRecord::find();
        if ($params) {
            $query->where($params);
        }
        $records = $query->all();
        $models = [];
        foreach ($records as $record) {
            $models[] = new Image($record->toArray());
        }
        return $models;
    }
    public function getImageById(int $id): ?Image
    {
        $record = ImageRecord::findOne(['id' => $id]);
        if (!$record) {
            return null;
        }
        return new Image($record->toArray());
    }

    public function getImageByTypeId(int $typeId, int $elementId): ?Image
    {
        $record = ImageRecord::findOne([
            'typeId' => $typeId,
            'elementId' => $elementId
        ]);
        if (!$record) {
            return null;
        }
        return new Image($record->toArray());
    }

    public function getImageByTypeHandle(string $typeHandle, int $elementId): ?Image
    {
        $record = ImageRecord::find()
            ->innerJoin(
                ImageTypeRecord::tableName() . ' t',
                't.id = ' . ImageRecord::tableName() . '.typeId'
            )
            ->where([
                't.handle' => $typeHandle,
                ImageRecord::tableName() . '.elementId' => $elementId,
            ])
            ->one();

        return $record ? new Image($record->toArray()) : null;
    }

    /**
     * @return Image[]
     */
    public function getImagesForElement(Element $element): array
    {
        $records = ImageRecord::find()->where(['elementId' => $element->id])->all();
        $images = [];
        foreach ($records as $record) {
            $image = new Image($record->toArray());
            $image->setElement($element);
            $images[] = $image;
        }
        return $images;
    }
    public function getTableData(int $page, int $limit, int $type = null): array
    {
        $offset = ($page - 1) * $limit;
        /** @var ImageRecord[] $records */
        $query = ImageRecord::find();
        if ($type) {
            $query->where(['typeId' => $type]);
        }
        $total = $query->count();
        $query = $query->offset($offset)->limit($limit)->orderBy(['id' => SORT_DESC]);
        $records = $query->all();

        // Index by ID
        $elementMap = [];
        $typeMap = [];
        $assetMap = [];

        // Collect element IDs
        $elementIds = array_filter(array_column($records, 'elementId'));
        $assetIds = array_filter(array_column($records, 'assetId'));

        $elementTypes = Craft::$app->elements->getElementTypesByIds($elementIds);
        $elements = [];

        foreach ($elementTypes as $type) {
            $elements = array_merge($elements, Craft::$app->elements->createElementQuery($type)->id($elementIds)->all());
        }

        $types = Plugin::getInstance()->typeService->getAllTypes();
        $assets = Asset::find()->id($assetIds)->all();


        foreach ($elements as $el) {
            $elementMap[$el->id] = $el;
        }

        foreach ($types as $type) {
            $typeMap[$type->id] = $type;
        }

        foreach ($assets as $asset) {
            $assetMap[$asset->id] = $asset;
        }


        $tableData = [];
        foreach ($records as $record) {
            $tableData[] = [
                'title' => $record->id,
                'id' => $record->id,
                'url' => $this->getCpUrl($record->id),
                'type' => isset($typeMap[$record->typeId])
                    ? [
                        'title' => $typeMap[$record->typeId]->name,
                        'url'   => $typeMap[$record->typeId]->getCpEditUrl(),
                    ]
                    : null,

                'element' => isset($elementMap[$record->elementId])
                    ? [
                        'title' => $elementMap[$record->elementId]->title,
                        'url'   => $elementMap[$record->elementId]->getCpEditUrl(),
                    ]
                    : null,

                'asset' => isset($assetMap[$record->assetId])
                    ? [
                        'title' => $assetMap[$record->assetId]->title,
                        'url'   => $assetMap[$record->assetId]->getCpEditUrl(),
                    ]
                    : null,
            ];
        }

        $pagination = AdminTable::paginationLinks($page, $total, $limit);
        return [$pagination, $tableData];
    }
    /**
     * @throws Exception
     */
    public function saveGeneratedImage(Image $image, bool $runValidation = true): bool
    {
        if ($runValidation && !$image->validate()) {
            return false;
        }

        if ($image->id === null) {
            $record = new ImageRecord();
        } else {
            $record = ImageRecord::findOne($image->id);
        }

        $record->assetId = $image->assetId;
        $record->elementId =  $image->elementId;
        $record->typeId = $image->typeId;
        $res =  $record->save(false);
        $image->id = $record->id;
        return $res;
    }

    /**
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function deleteGeneratedImage(int $id): bool
    {
        $record = ImageRecord::findOne(['id' => $id]);
        if (!$record) {
            return true;
        }
        $assetId = $record->assetId;
        if ($assetId) {
            $asset = Asset::find()->id($assetId)->one();
            if ($asset) {
                $this->_onDeletingAsset = true;
                Craft::$app->getElements()->deleteElement($asset);
            }
        }
        $this->_onDeletingAsset = false;
        return $record?->delete();
    }

    public function handleOnDeleteAsset(Asset $asset): void
    {
        if ($this->_onDeletingAsset) {
            return;
        }
        ImageRecord::deleteAll(['assetId' => $asset->id]);
    }

    public function handleOnDeleteElement(Element $element): void
    {
        $count = ImageRecord::find()->where(['elementId' => $element->id])->count();
        if ($count) {
            Craft::$app->queue->push(new DeleteImagesForElementJob([
                'elementId' => $element->id,
            ]));
        }
    }


    public function deleteImageForElement(int $elementId): void
    {
        $this->_onDeletingAsset = true;
        $transaction = Craft::$app->getDb()->beginTransaction();
        try{
            $assetIds = ImageRecord::find()->where(['elementId' => $elementId])->select('assetId')->column();
            $assets = Asset::find()->id($assetIds)->all();
            foreach ($assets as $asset) {
                Craft::$app->getElements()->deleteElement($asset);
            }
            ImageRecord::deleteAll(['elementId' => $elementId]);
            $transaction->commit();
        }catch (\Exception| Throwable $e){
            Craft::error("Failed to delete images for id: {$elementId} : {$e->getMessage()}", __METHOD__);
            $transaction->rollBack();
        }
        $this->_onDeletingAsset = false;
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws Exception
     * @throws SyntaxError
     * @throws VolumeException
     * @throws ErrorException
     * @throws \yii\base\Exception
     * @throws Throwable
     */
    public function generateImage(Image $image): void
    {
        $settings = Plugin::getInstance()->getSettings();

        $type = $image->getType();
        $element = $image->getElement();


        try {
            $html = Craft::$app->getView()->renderTemplate(
                $type->template, ['element' => $element], View::TEMPLATE_MODE_SITE
            );

            $imageData = $this->generateImageFromHtml($html, $type);
            $filename = $this->generateFilename($type->format, $element);
            $volume = Craft::$app->volumes->getVolumeByHandle($settings->assetVolumeHandle);
            if (!$volume) {
                throw new RuntimeException("Volume not found: {$settings->assetVolumeHandle}");
            }

            $folderPath = $settings->assetFolderPath . '/' . $type->handle;
            $folder = Craft::$app->getAssets()->ensureFolderByFullPathAndVolume($folderPath, $volume);

            $asset = $this->saveAssetFromImageData($imageData, $filename, $volume->id, $folder->id, $image->assetId);

            $image->setAsset($asset);
            $this->saveGeneratedImage($image);
        } catch (\Exception|Throwable $e) {
            Craft::error("Failed to generate image with element id: " . $element->id . 'and type: ' . $type->handle . ' with reasons: ' .$e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    public function getCpUrl(int $id): string
    {
        return UrlHelper::cpUrl('image-generator/images/' . $id);
    }

    public function getGenerateUrl(int $generatedImageId): string
    {
        return UrlHelper::actionUrl('image-generator/image/generate', [
            'id' => $generatedImageId,
        ]);
    }
    /**
     * @throws \yii\base\Exception
     * @throws ErrorException
     * @throws Throwable
     */
    private function saveAssetFromImageData(string $imageData, string $filename, int $volumeId, int $folderId, int $assetId = null): Asset
    {

        $tempPath = Craft::$app->path->getTempPath() . '/' . uniqid();
        FileHelper::writeToFile($tempPath, $imageData);

        if ($assetId !== null) {
            $asset = Asset::find()->id($assetId)->one();
            if ($asset) {
                $asset->setScenario(Asset::SCENARIO_REPLACE);
            } else {
                $asset = new Asset();
            }
        } else {
            $asset = new Asset();
        }

        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->volumeId = $volumeId;
        $asset->newFolderId = $folderId;
        $asset->avoidFilenameConflicts = true;
        if(!Craft::$app->getElements()->saveElement($asset)) {
            throw new RuntimeException('Unable to save asset');
        }
        return $asset;
    }


    private function generateFilename(string $format, Element $element): string
    {
        $slug = $element->id . '-' . ($element->slug ?? StringHelper::toKebabCase($element->title ?? '-image')) . '-' . StringHelper::randomString(4);
        return "ig-$slug.$format";
    }
    private function generateImageFromHtml(string $html, ImageType $type): ?string
    {
        $settings = Plugin::getInstance()->getSettings();
        $browsershot = Browsershot::html($html)
            ->setNodeBinary('/usr/local/bin/node')
            ->setNpmBinary('/usr/local/bin/npm')
            ->setChromePath("/usr/bin/chromium")
            ->noSandbox()
            ->waitUntilNetworkIdle()
            ->ignoreHttpsErrors()
            ->deviceScaleFactor(1)
            ->format($type->format);

        if ($settings->nodePath) {
            $browsershot->setNodeBinary($settings->nodePath);
        }

        if ($settings->npmPath) {
            $browsershot->setNpmBinary($settings->npmPath);
        }

        if ($settings->chromePath) {
            $browsershot->setChromePath($settings->chromePath);
        }

        if ($type->width && $type->height) {
            $browsershot->windowSize($type->width, $type->height);
        }

        if ($settings->disableWebSecurity) {
            $browsershot->setOption('args', [
                '--disable-web-security',
            ]);
        }

        $browsershot->quality($type->quality);

        return $browsershot->screenshot();
    }

}