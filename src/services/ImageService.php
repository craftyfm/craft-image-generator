<?php

namespace craftyfm\imagegenerator\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craftyfm\imagegenerator\models\GeneratedImage;
use craftyfm\imagegenerator\models\GeneratedImageType;
use craftyfm\imagegenerator\models\Settings;
use craftyfm\imagegenerator\Plugin;
use craftyfm\imagegenerator\records\GeneratedImageRecord;
use craftyfm\imagegenerator\records\GeneratedImageTypeRecord;
use Spatie\Browsershot\Browsershot;
use Throwable;
use yii\db\Exception;
use yii\db\StaleObjectException;

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


        $record = new GeneratedImageRecord($image->toArray());
        return $record->save(false);
    }

    /**
     * @throws \Throwable
     * @throws StaleObjectException
     */
    public function deleteGeneratedImage(int $id): bool
    {
        $record = GeneratedImageRecord::findOne(['id' => $id]);
        return $record?->delete();
    }

    public function generateImage(Element $element, GeneratedImageType $type)
    {

    }


    private function generateImageFromHtml(string $html, GeneratedImageType $type): ?string
    {
        $settings = Plugin::getInstance()->getSettings();
        try {

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
            if ($type->format === 'jpg' || $type->format === 'jpeg') {
                $browsershot->quality($type->quality);
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

}