<?php

namespace craftyfm\imagegenerator;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\DefineBehaviorsEvent;
use craft\events\ModelEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ElementHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craftyfm\imagegenerator\behaviors\ElementImageBehavior;
use craftyfm\imagegenerator\models\Settings;
use craftyfm\imagegenerator\services\ImageService;
use craftyfm\imagegenerator\services\TypeService;
use craftyfm\imagegenerator\variables\ImageGenerator;
use yii\base\Event;

/**
 *  Image Generator plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @property ImageService $imageService
 * @property TypeService $typeService
 * @author craftyfm
 * @copyright craftyfm
 * @license MIT
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'imageService' => ImageService::class,
                'typeService' => TypeService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        $this->attachEventHandlers();

        Craft::$app->onInit(function() {
            $this->registerCpRoutes();
        });

        // Register project config event listeners
        $this->_registerProjectConfigEventListeners();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Image Generator';
        $item['url'] = 'image-generator';
        $item['subnav'] = [
            'types' => ['label' => 'Types', 'url' => 'image-generator/types'],
            'images' => ['label' => 'Images', 'url' => 'image-generator/images'],
        ];

        return $item;
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('image-generator/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
//        // Attach behavior to Entry elements
//        Event::on(
//            Entry::class,
//            Model::EVENT_DEFINE_BEHAVIORS,
//            function(DefineBehaviorsEvent $event) {
//                $event->behaviors['Image'] = ElementImageBehavior::class;
//            }
//        );


        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $e) {
                /** @var CraftVariable $variable */
                $variable = $e->sender;

                // Attach a service:
                $variable->set('imageGenerator', ImageGenerator::class);
            }
        );
    }

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // Image routes
                $event->rules['image-generator/generate/<elementId:\\d+>'] = 'image-generator/image/generate';
                $event->rules['image-generator/bulk-generate'] = 'image-generator/image/bulk-generate';
                
                // Type routes
                $event->rules['image-generator'] = 'image-generator/type/index';
                $event->rules['image-generator/types'] = 'image-generator/type/index';
                $event->rules['image-generator/types/new'] = 'image-generator/type/edit';
                $event->rules['image-generator/types/<id:\\d+>'] = 'image-generator/type/edit';
            }
        );
    }

    private function _registerProjectConfigEventListeners(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        $projectConfigService
            ->onAdd(TypeService::CONFIG_TYPE_KEY . '.{uid}', [$this->typeService, 'handleChangedType'])
            ->onUpdate(TypeService::CONFIG_TYPE_KEY . '.{uid}', [$this->typeService, 'handleChangedType'])
            ->onRemove(TypeService::CONFIG_TYPE_KEY . '.{uid}', [$this->typeService, 'handleDeletedType']);
    }
}