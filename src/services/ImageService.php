<?php

namespace craftyfm\imagegenerator\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Asset;
use craft\errors\VolumeException;
use craft\feedme\helpers\AssetHelper;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\web\View;
use craftyfm\imagegenerator\models\GeneratedImage;
use craftyfm\imagegenerator\models\GeneratedImageType;
use craftyfm\imagegenerator\models\Settings;
use craftyfm\imagegenerator\Plugin;
use craftyfm\imagegenerator\records\GeneratedImageRecord;
use craftyfm\imagegenerator\records\GeneratedImageTypeRecord;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\ErrorException;
use yii\db\Exception;
use yii\db\StaleObjectException;
use yii\web\NotFoundHttpException;

class ImageService extends Component
{

    public function getImageById(int $id): ?GeneratedImage
    {
        $record = GeneratedImageRecord::findOne(['id' => $id]);
        if (!$record) {
            return null;
        }
        return new GeneratedImage($record->toArray());
    }

    public function getImageByTypeId(int $typeId, int $elementId): ?GeneratedImage
    {
        $record = GeneratedImageRecord::findOne([
            'typeId' => $typeId,
            'elementId' => $elementId
        ]);
        if (!$record) {
            return null;
        }
        return new GeneratedImage($record->toArray());
    }

    public function getImageByTypeHandle(string $typeHandle, int $elementId): ?GeneratedImage
    {
        $record = GeneratedImageRecord::find()
            ->innerJoin(
                GeneratedImageTypeRecord::tableName() . ' t',
                't.id = ' . GeneratedImageRecord::tableName() . '.typeId'
            )
            ->where([
                't.handle' => $typeHandle,
                GeneratedImageRecord::tableName() . '.elementId' => $elementId,
            ])
            ->one();

        return $record ? new GeneratedImage($record->toArray()) : null;
    }

    /**
     * @throws Exception
     */
    public function saveGeneratedImage(GeneratedImage $image, bool $runValidation = true): bool
    {
        if ($runValidation && !$image->validate()) {
            return false;
        }

        if ($image->id === null) {
            $record = new GeneratedImageRecord();
        } else {
            $record = GeneratedImageRecord::findOne($image->id);
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
        $record = GeneratedImageRecord::findOne(['id' => $id]);
        if (!$record) {
            return true;
        }
        if ($record->assetId) {
            $asset = Asset::find()->id($id)->one();
            if (!$asset) {
                Craft::$app->getElements()->deleteElement($asset);
            }
        }
        return $record?->delete();
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
    public function generateImage(Element $element, GeneratedImageType $type): GeneratedImage
    {
        $settings = Plugin::getInstance()->getSettings();

        //check if the element has generated image with current type or not.
        $generatedImage = $this->getImageByTypeId($type->id, $element->id);

        if (!$generatedImage) {
            $generatedImage = new GeneratedImage(
                [
                    'elementId' => $element->id,
                    'typeId' => $type->id,
                ]
            );
        }
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
            $folder = Craft::$app->getAssets()->ensureFolderByFullPathAndVolume($settings->assetFolderPath, $volume);

            $asset = $this->saveAssetFromImageData($imageData, $filename, $volume->id, $folder->id, $generatedImage->assetId);

            $generatedImage->setAsset($asset);
            $this->saveGeneratedImage($generatedImage);
        } catch (\Exception|Throwable $e) {
            Craft::error("Failed to generate image with element id: " . $element->id . 'and type: ' . $type->handle . ' with reasons: ' .$e->getMessage(), __METHOD__);
            throw $e;
        }

        return $generatedImage;
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
        $slug = $element->id . '-' . ($element->slug ?? StringHelper::toKebabCase($element->title ?? '-image'));
        return "ig-$slug.$format";
    }
    private function generateImageFromHtml(string $html, GeneratedImageType $type): ?string
    {
        $settings = Plugin::getInstance()->getSettings();
        $browsershot = Browsershot::html($html)
            ->setNodeBinary('/usr/local/bin/node')
            ->setNpmBinary('/usr/local/bin/npm')
            ->setChromePath("/usr/bin/chromium")
            ->noSandbox()
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

        $browsershot->quality($type->quality);

        return $browsershot->screenshot();
    }

}