<?php

namespace craftyfm\imagegenerator\records;

use craft\db\ActiveRecord;
use craftyfm\imagegenerator\helper\Table;

/**
 * @property string $handle
 * @property int $width
 * @property int $height
 * @property string $format
 * @property int $quality
 * @property string $name
 * @property int $id
 * @property string $template
 */
class GeneratedImageTypeRecord extends ActiveRecord
{

    public static function tableName(): string
    {
        return Table::GENERATED_IMAGE_TYPES_TABLE;
    }
}