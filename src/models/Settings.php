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
     * @var array Mapping of element types to template paths
     * Format: ['craft\elements\Entry' => ['sectionHandle' => 'template/path']]
     */
    public array $elementTemplateMapping = [
        'craft\elements\Entry' => []
    ];

    /**
     * @var string|null Asset volume handle where generated images will be stored
     */
    public ?string $assetVolumeHandle = null;

    /**
     * @var string|null Folder path within the asset volume
     */
    public ?string $assetFolderPath = '-images';

    public string $imageFormat = 'jpg';

    public int $imageQuality = 90;

    /**
     * @var array Additional Browsershot options
     */
    public array $browsershotOptions = [];

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'assetVolumeHandle',
                'assetFolderPath',
            ],
        ];
        return $behaviors;
    }

    public function rules(): array
    {
        return [
            [['elementTemplateMapping'], ArrayValidator::class],
            [['assetVolumeHandle', 'assetFolderPath'], 'string'],
            [['browsershotOptions'], ArrayValidator::class],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'elementTemplateMapping' => 'Element Template Mapping',
            'assetVolumeHandle' => 'Asset Volume',
            'assetFolderPath' => 'Asset Folder Path',
            'browsershotOptions' => 'Browsershot Options',
        ];
    }
}