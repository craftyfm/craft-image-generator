<?php

namespace craftyfm\imagegenerator\services;

use Craft;
use craft\base\Component;
use craft\errors\BusyResourceException;
use craft\errors\StaleResourceException;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craftyfm\imagegenerator\helper\Table;
use craftyfm\imagegenerator\models\ImageType;
use craftyfm\imagegenerator\records\ImageTypeRecord;
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
     * @return ImageType[]
     */
    public function getAllTypes(): array
    {
        $records = ImageTypeRecord::find()->all();
        $models = [];
        foreach ($records as $record) {
            $models[] = new ImageType($record->toArray());

        }
        return $models;
    }

    public function getTypeById(int $id): ?ImageType
    {
        $record = ImageTypeRecord::findOne(['id' => $id]);
        if (!$record) {
            return null;
        }
        return new ImageType($record->toArray());
    }

    public function getTypeByHandle(string $handle): ?ImageType
    {
        $record = ImageTypeRecord::findOne(['handle' => $handle]);
        if (!$record) {
            return null;
        }
        return new ImageType($record->toArray());
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
    public function saveType(ImageType $type, bool $runValidation = true): bool
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

        $record = ImageTypeRecord::findOne(['uid' => $uid]);
        if (!$record) {
            $isNewType = true;
            $record = new ImageTypeRecord();
        }

        $record->name = $data['name'];
        $record->handle = $data['handle'];
        $record->width = $data['width'] ?? null;
        $record->height = $data['height'] ?? null;
        $record->format = $data['format'];
        $record->quality = $data['quality'];
        $record->template = $data['template'];
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

    public function deleteType(ImageType $type): bool
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

        $record = ImageTypeRecord::findOne(['uid' => $uid]);
        $record?->delete();
    }

    private function _getTypeConfig(ImageType $type): array
    {
        return [
            'name' => $type->name,
            'handle' => $type->handle,
            'width' => $type->width,
            'height' => $type->height,
            'format' => $type->format,
            'quality' => $type->quality,
            'template' => $type->template,
        ];
    }
}