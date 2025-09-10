<?php

namespace craftyfm\imagegenerator\models;

use craft\base\Model;

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

    public bool $disableWebSecurity = false;
    public function rules(): array
    {
        return [
            [['assetVolumeHandle', 'assetFolderPath'], 'string'],
            [['assetVolumeHandle', 'assetFolderPath'], 'required'],
        ];
    }

}