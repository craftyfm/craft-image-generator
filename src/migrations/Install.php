<?php

namespace craftyfm\imagegenerator\migrations;

use craft\db\Migration;
use craftyfm\imagegenerator\helper\Table;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        /**
         * Table 1: generatedimagetypes
         */
        $this->createTable(Table::GENERATED_IMAGE_TYPES_TABLE, [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull()->unique(),
            'template' => $this->string()->notNull(),
            'width' => $this->integer(),
            'height' => $this->integer(),
            'format' => $this->string(10)->notNull()->defaultValue('jpg'),
            'quality' => $this->integer()->defaultValue(80),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        /**
         * Table 2: generatedimages
         */
        $this->createTable(Table::GENERATED_IMAGE_TABLE, [
            'id' => $this->primaryKey(),
            'assetId' => $this->integer(),
            'elementId' => $this->integer()->notNull(),
            'typeId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Indexes
        $this->createIndex(null, Table::GENERATED_IMAGE_TABLE, ['assetId'], false);
        $this->createIndex(null, Table::GENERATED_IMAGE_TABLE, ['elementId'], false);
        $this->createIndex(null, Table::GENERATED_IMAGE_TABLE, ['typeId'], false);
        $this->createIndex(
            null,
            Table::GENERATED_IMAGE_TABLE,
            ['elementId', 'typeId'],
            true // unique = true
        );
        // Foreign Keys for generatedimages
        $this->addForeignKey(
            null,
            Table::GENERATED_IMAGE_TABLE,
            'assetId',
            '{{%assets}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            Table::GENERATED_IMAGE_TABLE,
            'elementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            Table::GENERATED_IMAGE_TABLE,
            'typeId',
            Table::GENERATED_IMAGE_TYPES_TABLE,
            'id',
            'CASCADE',
            'CASCADE'
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        // Place uninstallation code here...
        $this->dropTableIfExists(Table::GENERATED_IMAGE_TABLE);
        $this->dropTableIfExists(Table::GENERATED_IMAGE_TYPES_TABLE);
        return true;
    }
}
