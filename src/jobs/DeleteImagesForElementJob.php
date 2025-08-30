<?php

namespace craftyfm\imagegenerator\jobs; // ← change to your plugin/module namespace

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\queue\BaseJob;
use craftyfm\imagegenerator\Plugin;
use yii\base\InvalidConfigException;

/**
 * Queue job to delete one or many elements.
 *
 * Usage:
 * Craft::$app->queue->push(new DeleteElementJob([
 *     'elementIds' => [123, 456],
 *     'elementType' => \craft\elements\Entry::class, // or Asset::class, Category::class, etc.
 *     'siteId' => null,    // optional; if set, fetches elements in a specific site
 *     'hardDelete' => false, // false = move to trash; true = permanently delete
 * ]));
 */
class DeleteImagesForElementJob extends BaseJob
{
    public int $elementId;

    public function execute($queue): void
    {
        Plugin::getInstance()->imageService->deleteImageForElement($this->elementId);
    }

    protected function defaultDescription(): ?string
    {
        return "Deleting generated images for element id : {$this->elementId}";
    }
}
