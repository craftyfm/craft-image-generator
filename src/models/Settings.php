<?php

namespace craftyfm\imagegenerator\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\validators\ArrayValidator;

/**
 *  Image Generator settings
 */
class Settings extends Model
{

    /**
     * @var string|null Asset volume handle where generated images will be stored
     */
    public ?string $assetVolumeHandle = null;

    /**
     * @var string|null Folder path within the asset volume
     */
    public ?string $assetFolderPath = 'generated-images';

    public ?string $nodePath = null;
    public ?string $npmPath = null;
    public ?string $chromePath = null;

    public function rules(): array
    {
        return [
            [['elementTemplateMapping'], ArrayValidator::class],
            [['assetVolumeHandle', 'assetFolderPath'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'elementTemplateMapping' => 'Element Template Mapping',
            'assetVolumeHandle' => 'Asset Volume',
            'assetFolderPath' => 'Asset Folder Path',
        ];
    }
}