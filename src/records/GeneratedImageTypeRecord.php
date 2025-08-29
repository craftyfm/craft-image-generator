<?php

namespace craftyfm\imagegenerator\records;

use craft\db\ActiveRecord;
use craftyfm\imagegenerator\helper\Table;

/**
 * @property mixed|null $handle
 * @property mixed|null $width
 * @property mixed|null $height
 * @property mixed|null $format
 * @property mixed|null $quality
 * @property mixed|null $name
 * @property mixed|null $id
 */
class GeneratedImageTypeRecord extends ActiveRecord
{

    public static function tableName(): string
    {
        return Table::GENERATED_IMAGE_TYPES_TABLE;
    }
}