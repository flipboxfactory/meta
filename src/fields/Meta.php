<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\fields;

use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\behaviors\FieldLayoutBehavior;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use craft\models\FieldLayout;
use craft\validators\ArrayValidator;
use flipbox\meta\db\MetaQuery;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\Meta as MetaPlugin;
use flipbox\meta\records\Meta as MetaRecord;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @method setFieldLayout(FieldLayout $fieldLayout)
 * @method FieldLayout getFieldLayout()
 */
class Meta extends Field implements EagerLoadingFieldInterface
{
    /**
     * Default layout template
     */
    const DEFAULT_TEMPLATE = FieldHelper::TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'layout';

    /**
     * Maximum number of meta
     *
     * @var int|null
     */
    public $max;

    /**
     * Minimum number of meta
     *
     * @var int|null
     */
    public $min;

    /**
     * @var string
     */
    public $selectionLabel = "Add meta";

    /**
     * Whether each site should get its own unique set of meta
     *
     * @var int
     */
    public $localize = false;

    /**
     * @var int|null
     */
    public $fieldLayoutId;

    /**
     * @var string
     */
    protected $template = self::DEFAULT_TEMPLATE;

    /**
     * Todo - Remove this (don't like it)
     *
     * @var bool
     */
    public $hasFieldErrors = false;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('meta', 'Meta');
    }

    /**
     * @inheritdoc
     */
    public static function supportedTranslationMethods(): array
    {
        return [
            self::TRANSLATION_METHOD_SITE,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function settingsAttributes(): array
    {
        return [
            'max',
            'min',
            'selectionLabel',
            'fieldLayoutId',
            'template',
            'localize'
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => MetaElement::class
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(
            parent::rules(),
            [
                [
                    [
                        'min',
                        'max'
                    ],
                    'integer',
                    'min' => 0
                ]
            ]
        );
    }


    /*******************************************
     * VALUE
     *******************************************/

    /**
     * @inheritdoc
     */
    public function isValueEmpty($value, ElementInterface $element): bool
    {
        /** @var MetaQuery $value */
        return $value->count() === 0;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        return MetaPlugin::getInstance()->getFields()->serializeValue($this, $value, $element);
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        return MetaPlugin::getInstance()->getFields()->normalizeValue($this, $value, $element);
    }


    /*******************************************
     * QUERY
     *******************************************/

    /**
     * @inheritdoc
     */
    public function modifyElementsQuery(ElementQueryInterface $query, $value)
    {
        return MetaPlugin::getInstance()->getFields()->modifyElementsQuery($this, $query, $value);
    }


    /*******************************************
     * TEMPLATE GETTER/SETTER
     *******************************************/

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template ?: self::DEFAULT_TEMPLATE;
    }

    /**
     * @param $template
     * @return $this
     */
    public function setTemplate(string $template = null)
    {
        $this->template = $template;
        return $this;
    }


    /*******************************************
     * HTML
     *******************************************/

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        return MetaPlugin::getInstance()->getConfiguration()->getInputHtml($this, $value, $element);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return MetaPlugin::getInstance()->getConfiguration()->getSettingsHtml($this);
    }

    /**
     * @inheritdoc
     */
    public function getStaticHtml($value, ElementInterface $element): string
    {
        // Todo - implement this
        return '';
    }


    /*******************************************
     * VALIDATION
     *******************************************/

    /**
     * @inheritdoc
     */
    public function validate($attributeNames = null, $clearErrors = true): bool
    {
        // Run basic model validation first
        $validates = parent::validate($attributeNames, $clearErrors);

        // Run field validation as well
        if (!MetaPlugin::getInstance()->getConfiguration()->validate($this)) {
            $validates = false;
        }

        return $validates;
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [
            'validateMeta',
            [
                ArrayValidator::class,
                'min' => $this->min ?: null,
                'max' => $this->max ?: null,
                'tooFew' => Craft::t('app',
                    '{attribute} should contain at least {min, number} {min, plural, one{block} other{blocks}}.'),
                'tooMany' => Craft::t('app',
                    '{attribute} should contain at most {max, number} {max, plural, one{block} other{blocks}}.'),
                'skipOnEmpty' => false,
                'on' => Element::SCENARIO_LIVE,
            ],
        ];
    }

    /**
     * Validates an owner element’s Meta.
     *
     * @param ElementInterface $element
     */
    public function validateMeta(ElementInterface $element)
    {
        /** @var Element $element */
        /** @var MetaQuery $value */
        $value = $element->getFieldValue($this->handle);

        foreach ($value->all() as $i => $meta) {
            /** @var MetaElement $meta */

            if ($element->getScenario() === Element::SCENARIO_LIVE) {
                $meta->setScenario(Element::SCENARIO_LIVE);
            }

            if (!$meta->validate()) {
                $element->addModelErrors($meta, "{$this->handle}[{$i}]");
            }
        }
    }


    /*******************************************
     * SEARCH
     *******************************************/

    /**
     * @param mixed $value
     * @param Element|ElementInterface $element
     * @return string
     */
    public function getSearchKeywords($value, ElementInterface $element): string
    {
        /** @var MetaQuery $value */

        $keywords = [];
        $contentService = Craft::$app->getContent();

        /** @var MetaElement $meta */
        foreach ($value->all() as $meta) {
            $originalContentTable = $contentService->contentTable;
            $originalFieldContext = $contentService->fieldContext;

            $contentService->contentTable = $meta->getContentTable();
            $contentService->fieldContext = $meta->getFieldContext();

            /** @var Field $field */
            foreach (Craft::$app->getFields()->getAllFields() as $field) {
                $fieldValue = $meta->getFieldValue($field->handle);
                $keywords[] = $field->getSearchKeywords($fieldValue, $element);
            }

            $contentService->contentTable = $originalContentTable;
            $contentService->fieldContext = $originalFieldContext;
        }

        return parent::getSearchKeywords($keywords, $element);
    }


    /*******************************************
     * EAGER LOADING
     *******************************************/

    /**
     * @inheritdoc
     */
    public function getEagerLoadingMap(array $sourceElements)
    {
        // Get the source element IDs
        $sourceElementIds = [];

        foreach ($sourceElements as $sourceElement) {
            $sourceElementIds[] = $sourceElement->id;
        }

        // Return any relation data on these elements, defined with this field
        $map = (new Query())
            ->select(['ownerId as source', 'id as target'])
            ->from([MetaRecord::tableName()])
            ->where([
                'fieldId' => $this->id,
                'ownerId' => $sourceElementIds,
            ])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        return [
            'elementType' => MetaElement::class,
            'map' => $map,
            'criteria' => ['fieldId' => $this->id]
        ];
    }

    /*******************************************
     * FIELDS
     *******************************************/

    /**
     * Fields are
     * @param $fields
     */
    public function setFields(array $fields)
    {
        $defaultFieldConfig = [
            'type' => null,
            'name' => null,
            'handle' => null,
            'instructions' => null,
            'required' => false,
            'translationMethod' => Field::TRANSLATION_METHOD_NONE,
            'translationKeyFormat' => null,
            'settings' => null,
        ];

        foreach ($fields as $fieldId => $fieldConfig) {
            if (!$fieldConfig instanceof FieldInterface) {

                /** @noinspection SlowArrayOperationsInLoopInspection */
                $fieldConfig = array_merge($defaultFieldConfig, $fieldConfig);

                $fields[$fieldId] = Craft::$app->getFields()->createField([
                    'type' => $fieldConfig['type'],
                    'id' => $fieldId,
                    'name' => $fieldConfig['name'],
                    'handle' => $fieldConfig['handle'],
                    'instructions' => $fieldConfig['instructions'],
                    'required' => (bool)$fieldConfig['required'],
                    'translationMethod' => $fieldConfig['translationMethod'],
                    'translationKeyFormat' => $fieldConfig['translationKeyFormat'],
                    'settings' => $fieldConfig['settings'],
                ]);
            }
        }

        $this->getFieldLayout()->setFields($fields);
    }

    /*******************************************
     * EVENTS
     *******************************************/

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        MetaPlugin::getInstance()->getConfiguration()->afterSave($this);
        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        MetaPlugin::getInstance()->getConfiguration()->beforeDelete($this);
        return parent::beforeDelete();
    }

    /**
     * @inheritdoc
     */
    public function afterElementSave(ElementInterface $element, bool $isNew)
    {
        MetaPlugin::getInstance()->getFields()->afterElementSave($this, $element);
        parent::afterElementSave($element, $isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeElementDelete(ElementInterface $element): bool
    {
        if (!MetaPlugin::getInstance()->getFields()->beforeElementDelete($this, $element)) {
            return false;
        }
        return parent::beforeElementDelete($element);
    }
}
