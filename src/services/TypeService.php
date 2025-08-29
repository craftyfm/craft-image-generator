<?php

namespace craftyfm\imagegenerator\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\errors\BusyResourceException;
use craft\errors\StaleResourceException;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craftyfm\imagegenerator\helper\Table;
use craftyfm\imagegenerator\models\GeneratedImageType;
use craftyfm\imagegenerator\records\GeneratedImageTypeRecord;
use DateTime;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\StaleObjectException;
use yii\web\ServerErrorHttpException;

class TypeService extends Component
{
    public const CONFIG_TYPE_KEY = 'imageGenerator.types';


    /**
     * @return GeneratedImageType[]
     */
    public function getAllTypes(): array
    {
        $records = GeneratedImageTypeRecord::find()->all();
        $models = [];
        foreach ($records as $record) {
            $models[] = new GeneratedImageType($record->toArray());

        }
        return $models;
    }

    public function getTypeById(int $id): ?GeneratedImageType
    {
        $record = GeneratedImageTypeRecord::findOne(['id' => $id]);
        if (!$record) {
            return null;
        }
        return new GeneratedImageType($record->toArray());
    }

    public function getTypeByHandle(string $handle): ?GeneratedImageType
    {
        $record = GeneratedImageTypeRecord::findOne(['handle' => $handle]);
        if (!$record) {
            return null;
        }
        return new GeneratedImageType($record->toArray());
    }

    /**
     * @throws NotSupportedException
     * @throws InvalidConfigException
     * @throws ServerErrorHttpException
     * @throws StaleResourceException
     * @throws BusyResourceException
     * @throws ErrorException
     * @throws Exception
     * @throws \Exception
     */
    public function saveType(GeneratedImageType $type, bool $runValidation = true): bool
    {
        $isNewType = !$type->id;

        if ($runValidation && !$type->validate()) {
            Craft::info('Type not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewType) {
            $type->uid = StringHelper::UUID();
        } else if (!$type->uid) {
            $type->uid = Db::uidById(Table::GENERATED_IMAGE_TYPES_TABLE, $type->id);
        }


        // Save to project config
        $projectConfig = Craft::$app->getProjectConfig();
        $configData = $this->_getTypeConfig($type);

        $configPath = self::CONFIG_TYPE_KEY . '.' . $type->uid;
        $projectConfig->set($configPath, $configData);

        if ($isNewType) {
            $type->id = Db::idByUid(Table::GENERATED_IMAGE_TYPES_TABLE, $type->uid);
        }
        return true;
    }

    /**
     * @throws \yii\db\Exception
     */
    public function handleChangedType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $data = $event->newValue;

        $record = GeneratedImageTypeRecord::findOne(['uid' => $uid]);
        if (!$record) {
            $isNewType = true;
            $record = new GeneratedImageTypeRecord();
        }

        $record->name = $data['name'];
        $record->handle = $data['handle'];
        $record->width = $data['width'] ?? null;
        $record->height = $data['height'] ?? null;
        $record->format = $data['format'];
        $record->quality = $data['quality'];
        $record->uid = $uid;

        $record->save(false);

    }

    public function deleteTypeById(int $id): bool
    {
        $type = $this->getTypeById($id);
        if (!$type) {
            return false;
        }

        return $this->deleteType($type);
    }

    public function deleteType(GeneratedImageType $type): bool
    {
        $uid = $type->uid;
        Craft::$app->projectConfig->remove(self::CONFIG_TYPE_KEY . '.' . $uid);
        return true;
    }

    /**
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function handleDeletedType(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];

        $record = GeneratedImageTypeRecord::findOne(['uid' => $uid]);
        $record?->delete();
    }

    private function _getTypeConfig(GeneratedImageType $type): array
    {
        return [
            'name' => $type->name,
            'handle' => $type->handle,
            'width' => $type->width,
            'height' => $type->height,
            'format' => $type->format,
            'quality' => $type->quality,
        ];
    }
}