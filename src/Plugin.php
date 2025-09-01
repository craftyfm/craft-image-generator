<?php

namespace craftyfm\imagegenerator;

use Craft;
use craft\base\Element;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ElementHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craftyfm\imagegenerator\models\Settings;
use craftyfm\imagegenerator\services\ImageService;
use craftyfm\imagegenerator\services\TypeService;
use craftyfm\imagegenerator\variables\ImageGenerator;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\Exception;

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
            'images' => ['label' => 'Images', 'url' => 'image-generator/images'],
            'types' => ['label' => 'Types', 'url' => 'image-generator/types'],
        ];

        return $item;
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * @throws SyntaxError
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
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
            Asset::class,
            Element::EVENT_AFTER_DELETE,
            function (Event $event) {

                /** @var Asset $asset */
                $asset = $event->sender;

                if (ElementHelper::isDraftOrRevision($asset)) {
                    return;
                }
                Plugin::getInstance()->imageService->handleOnDeleteAsset($asset);
            }
        );

        Event::on(
            Element::class,
            Element::EVENT_AFTER_DELETE,
            function (Event $event) {

                /** @var Element $element */
                $element = $event->sender;

                if ($element instanceof Asset) {
                    return;
                }
                if (ElementHelper::isDraftOrRevision($element)) {
                    return;
                }

                Plugin::getInstance()->imageService->handleOnDeleteElement($element);
            }
        );

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

        Event::on(
            Element::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            static function(DefineHtmlEvent $event) {

                /** @var Element $element */
                $element = $event->sender;
                if (ElementHelper::isDraftOrRevision($element)) {
                    $mainElement = $element->getCanonical();
                } else {
                    $mainElement = $element;
                }
                $generatedImages = Plugin::getInstance()->imageService->getImagesForElement($mainElement);

                if ($generatedImages) {
                    $html = Craft::$app->view->renderTemplate('image-generator/_cp/element-sidebar', [
                        'images' => $generatedImages,
                    ]);
                    $event->html .= $html;
                }
            });
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
                $event->rules['image-generator'] = 'image-generator/image/index';
                $event->rules['image-generator/images'] = 'image-generator/image/index';
                $event->rules['image-generator/images/<type:{handle}>'] = 'image-generator/image/index';

                $event->rules['image-generator/images/bulk-regenerate'] = 'image-generator/image/bulk-regenerate';
                $event->rules['image-generator/images/bulk-regenerate/<typeId:\\d+>'] = 'image-generator/image/bulk-regenerate';

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