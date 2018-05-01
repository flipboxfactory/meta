<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\records\Field as FieldRecord;
use craft\services\Elements;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\fields\Meta as MetaFieldType;
use flipbox\meta\web\twig\variables\Meta as MetaVariable;
use yii\base\Event;
use yii\log\Logger;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Meta extends Plugin
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        // Element
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = MetaElement::class;
            }
        );

        // Field type
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = MetaFieldType::class;
            }
        );

        // Twig variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('meta', MetaVariable::class);
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function beforeUninstall(): bool
    {
        // Get field all fields associated to this plugin
        $existingFieldRecords = Field::findAll([
            'type' => MetaFieldType::class
        ]);

        // Delete them
        foreach ($existingFieldRecords as $existingFieldRecord) {
            Craft::$app->getFields()->deleteFieldById($existingFieldRecord->id);
        }

        return true;
    }

    /*******************************************
     * SERVICES
     *******************************************/

    /**
     * @inheritdoc
     * @return services\Records
     */
    public function getRecords()
    {
        return $this->get('records');
    }

    /**
     * @inheritdoc
     * @return services\Elements
     */
    public function getElements()
    {
        return $this->get('elements');
    }

    /**
     * @inheritdoc
     * @return services\Fields
     */
    public function getFields()
    {
        return $this->get('fields');
    }

    /**
     * @inheritdoc
     * @return services\Configuration
     */
    public function getConfiguration()
    {
        return $this->get('configuration');
    }

    /*******************************************
     * LOGGING
     *******************************************/

    /**
     * Logs a trace message.
     * Trace messages are logged mainly for development purpose to see
     * the execution work flow of some code.
     * @param string $message the message to be logged.
     * @param string $category the category of the message.
     */
    public static function trace($message, string $category = null)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_TRACE, self::normalizeCategory($category));
    }

    /**
     * Logs an error message.
     * An error message is typically logged when an unrecoverable error occurs
     * during the execution of an application.
     * @param string $message the message to be logged.
     * @param string $category the category of the message.
     */
    public static function error($message, string $category = null)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, self::normalizeCategory($category));
    }

    /**
     * Logs a warning message.
     * A warning message is typically logged when an error occurs while the execution
     * can still continue.
     * @param string $message the message to be logged.
     * @param string $category the category of the message.
     */
    public static function warning($message, string $category = null)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_WARNING, self::normalizeCategory($category));
    }

    /**
     * Logs an informative message.
     * An informative message is typically logged by an application to keep record of
     * something important (e.g. an administrator logs in).
     * @param string $message the message to be logged.
     * @param string $category the category of the message.
     */
    public static function info($message, string $category = null)
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, self::normalizeCategory($category));
    }

    /**
     * @param string|null $category
     * @return string
     */
    private static function normalizeCategory(string $category = null)
    {
        $normalizedCategory = 'Meta';

        if ($category === null) {
            return $normalizedCategory;
        }

        return $normalizedCategory . ': ' . $category;
    }
}
