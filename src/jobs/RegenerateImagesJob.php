<?php

namespace craftyfm\imagegenerator\jobs;

use craft\errors\VolumeException;
use craft\queue\BaseJob;
use craftyfm\imagegenerator\Plugin;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\ErrorException;
use yii\db\Exception;

class RegenerateImagesJob extends BaseJob
{

    public array $imageIds = [];

    /**
     * @throws RuntimeError
     * @throws LoaderError
     * @throws \Throwable
     * @throws SyntaxError
     * @throws Exception
     * @throws VolumeException
     * @throws ErrorException
     * @throws \yii\base\Exception
     */
    public function execute($queue): void
    {
        if (!$this->imageIds) {
            return;
        }

        $this->setProgress($queue, 0, 'Starting…');

        $images = Plugin::getInstance()->imageService->getImages([
            'id' => $this->imageIds,
        ]);
        $total = count($images);

        $processed = 0;
        foreach ($images as $image) {
            $processed++;
            $percent = $processed / $total;
            $this->setProgress($queue, $percent, "Processing image {$processed} of {$total}");

            Plugin::getInstance()->imageService->generateImage($image);
        }

        $this->setProgress($queue, 100, 'Done');


    }

    public function defaultDescription(): ?string
    {
        return "Regenerating Generated images";
    }
}