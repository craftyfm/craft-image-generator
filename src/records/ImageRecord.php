<?php

namespace craftyfm\imagegenerator\records;

use craft\db\ActiveRecord;
use craftyfm\imagegenerator\helper\Table;

/**
 * @property null|int $assetId
 * @property int $elementId
 * @property int $typeId
 * @property int|null $id
 */
class ImageRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Table::GENERATED_IMAGE_TABLE;
    }
}