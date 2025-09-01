<?php

namespace craftyfm\imagegenerator\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\errors\VolumeException;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\models\Volume;
use craft\models\VolumeFolder;
use craft\records\Relations;
use craft\web\View;
use craftyfm\imagegenerator\models\Settings;
use craftyfm\imagegenerator\Plugin;
use Spatie\Browsershot\Browsershot;
use Throwable;
use yii\base\InvalidConfigException;

/**
 *  Image Service
 */
class OgImageService extends Component
{
    private const RELATION_TYPE = 'Image';

    /**
     * Generate  image for an element
     */
    public function generateImage(Element $element): ?Asset
    {
        $settings = Plugin::getInstance()->getSettings();

        try {
            // Get template path for this element
            $templatePath = $this->getTemplatePathForElement($element);
            if (!$templatePath) {
                Craft::info("No template configured for element type: " . get_class($element) . "with id:". $element->id, __METHOD__);
                return null;
            }

            // Render the template
            $html = $this->renderTemplate($templatePath, $element);

            if (!$html) {
                Craft::error("Failed to render template: {$templatePath}", __METHOD__);
                return null;
            }

            // Generate image using Browsershot
            $imageData = $this->generateImageFromHtml($html, $settings);

            if (!$imageData) {
                Craft::error("Failed to generate image from HTML", __METHOD__);
                return null;
            }

            // Save as Asset
            $asset = $this->saveAssetFromImageData($imageData, $element, $settings);
//            if ($asset) {
//                $this->saveElementImageRelation($element, $asset);
//            }

            return $asset;
        } catch (Throwable $e) {
            dd($e);
            Craft::error("Failed to generate  image for element {$element->id}: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Get the generated  image asset for an element
     */
    public function getImageForElement(Element $element): ?Asset
    {
        $relation = Relations::find()
            ->where([
                'sourceId' => $element->id,
                'sourceSiteId' => $element->siteId,
                'field' => self::RELATION_TYPE,
            ])
            ->one();

        if (!$relation) {
            return null;
        }

        return Asset::find()->id($relation->targetId)->one();
    }

    /**
     * Remove  image for an element
     */
    public function removeImageForElement(Element $element): bool
    {
        $asset = $this->getImageForElement($element);
        if ($asset) {
            // Delete the asset
            Craft::$app->elements->deleteElement($asset);

            // Remove the relation
            Relations::deleteAll([
                'sourceId' => $element->id,
                'sourceSiteId' => $element->siteId,
                'field' => self::RELATION_TYPE,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Bulk generate  images for elements
     */
    public function bulkGenerateImages(array $elementIds, bool $force = false): array
    {
        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($elementIds as $elementId) {
            $element = Craft::$app->elements->getElementById($elementId);
            if (!$element) {
                $results['failed']++;
                continue;
            }

            $existingImage = $this->getImageForElement($element);
            if ($existingImage && !$force) {
                $results['skipped']++;
                continue;
            }

            if ($force && $existingImage) {
                $this->removeImageForElement($element);
            }

            $asset = $this->generateImage($element);
            if ($asset) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get template path for element
     * @throws InvalidConfigException
     */
    private function getTemplatePathForElement(Element $element): ?string
    {
        $settings = Plugin::getInstance()->getSettings();
        $elementClass = get_class($element);
        if (!isset($settings->elementTemplateMapping[$elementClass])) {
            return null;
        }
        $mapping = $settings->elementTemplateMapping[$elementClass];

        // For entries, check by section handle
        if ($element instanceof Entry) {
            $sectionHandle = $element->getSection()->handle;
            return $mapping[$sectionHandle] ?? null;
        }

        // For other elements, use default template
        return $mapping['default'] ?? null;
    }

    /**
     * Render template with element data
     */
    private function renderTemplate(string $templatePath, Element $element): ?string
    {
        try {
            return Craft::$app->view->renderTemplate($templatePath, [
                'element' => $element,
                'entry' => $element, // Alias for backwards compatibility
            ], View::TEMPLATE_MODE_SITE);
        } catch (Throwable $e) {
            Craft::error("Failed to render template {$templatePath}: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Generate image from HTML using Browsershot
     */
    private function generateImageFromHtml(string $html, Settings $settings): ?string
    {
        try {
            if (!class_exists(Browsershot::class)) {
                throw new \Exception('Browsershot package is not installed. Please run: composer require spatie/browsershot');
            }

            $browsershot = Browsershot::html($html)
                ->setNodeBinary('/usr/local/bin/node')
                ->setNpmBinary('/usr/local/bin/npm')
                ->setChromePath("/usr/bin/chromium")
                ->noSandbox()
                ->windowSize(1200, 630)
                ->deviceScaleFactor(1)
                ->format($settings->imageFormat);

            if ($settings->imageFormat === 'jpg' || $settings->imageFormat === 'jpeg') {
                $browsershot->quality($settings->imageQuality);
            }

            // Apply additional options
            foreach ($settings->browsershotOptions as $method => $value) {
                if (method_exists($browsershot, $method)) {
                    $browsershot->$method($value);
                }
            }

            return $browsershot->screenshot();
        } catch (Throwable $e) {
            Craft::error("Browsershot failed: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Save image data as Asset
     */
    private function saveAssetFromImageData(string $imageData, Element $element, Settings $settings): ?Asset
    {
        try {
            // Get volume


            // Create temp file
            $tempPath = Craft::$app->path->getTempPath() . '/' . uniqid() . '.' . $settings->imageFormat;
            FileHelper::writeToFile($tempPath, $imageData);

            // Generate filename
            $filename = $this->generateFilename($element, $settings->imageFormat);

            // Create folder path
            $folderPath = $settings->assetFolderPath;

            $folder = $this->ensureFolderExists($volume, $folderPath);

            $asset = Asset::find()->filename($filename)->folderId($folder->id)->one();
            if (!$asset) {
                $asset = new Asset();
                $asset->setScenario(Asset::SCENARIO_CREATE);
            }

            // Create Asset
            $asset->tempFilePath = $tempPath;
            $asset->filename = $filename;
            $asset->newFolderId = $folder->id;
            $asset->volumeId = $volume->id;
            $asset->avoidFilenameConflicts = true;

            $res = Craft::$app->elements->saveElement($asset);

            if ($res) {
                return $asset;
            }

            Craft::error("Failed to save asset: " . implode(', ', $asset->getFirstErrors()), __METHOD__);
            return null;
        } catch (Throwable $e) {
            Craft::error("Failed to save asset: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Generate filename for  image
     */
    private function generateFilename(Element $element, string $format): string
    {
        $slug = $element->slug ?? StringHelper::toKebabCase($element->title ?? '-image');
        return "-{$slug}.{$format}";
    }

    /**
     * Ensure folder exists in volume
     * @throws VolumeException
     */
    private function ensureFolderExists(Volume $volume, string $folderPath): VolumeFolder
    {
        $folderId = null;
        $assetsService = Craft::$app->getAssets();

        $folder = $assetsService->findFolder([
            'volumeId' => $volume->id,
            'path' => $folderPath,
        ]);

        // Ensure that the folder exists
        if (!$folder) {
            $folder = $assetsService->ensureFolderByFullPathAndVolume($folderPath, $volume);
        }


        return $folder;
    }

    /**
     * Save element-to-asset relation
     */
    private function saveElementImageRelation(Element $element, Asset $asset): void
    {
        // Remove existing relation
        Relations::deleteAll([
            'sourceId' => $element->id,
            'sourceSiteId' => $element->siteId,
            'field' => self::RELATION_TYPE,
        ]);

        // Create new relation
        $relation = new Relations();
        $relation->sourceId = $element->id;
        $relation->sourceSiteId = $element->siteId;
        $relation->targetId = $asset->id;
        $relation->field = self::RELATION_TYPE;
        $relation->sortOrder = 1;
        $relation->save();
    }
}