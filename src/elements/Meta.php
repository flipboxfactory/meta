<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\elements;

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\validators\SiteIdValidator;
use flipbox\ember\helpers\ModelHelper;
use flipbox\meta\db\MetaQuery;
use flipbox\meta\fields\Meta as MetaField;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\Meta as MetaPlugin;
use flipbox\meta\traits\OwnerAttribute;
use yii\base\Exception;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Meta extends Element
{
    use OwnerAttribute;

    /**
     * @var int|null Field ID
     */
    public $fieldId;

    /**
     * @var int|null Owner site ID
     */
    public $ownerSiteId;

    /**
     * @var int|null Sort order
     */
    public $sortOrder;

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
    public static function refHandle()
    {
        return 'meta';
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @return MetaQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new MetaQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle)
    {
        $fieldId = $sourceElements[0]->fieldId;

        // Create field context (meta:{id})
        $fieldContext = FieldHelper::getContextById($fieldId);

        // Get all fields (by context)
        $fields = ArrayHelper::index(
            Craft::$app->getFields()->getAllFields($fieldContext),
            'handle'
        );

        // Does field exist?
        if (ArrayHelper::keyExists($handle, $fields)) {
            $contentService = Craft::$app->getContent();

            $originalFieldContext = $contentService->fieldContext;
            $contentService->fieldContext = $fieldContext;

            $map = parent::eagerLoadingMap($sourceElements, $handle);

            $contentService->fieldContext = $originalFieldContext;

            return $map;
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
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
                        'fieldId',
                        'ownerId',
                        'fieldId'
                    ],
                    'number',
                    'integerOnly' => true
                ],
                [
                    [
                        'ownerSiteId'
                    ],
                    SiteIdValidator::class
                ],
                [
                    [
                        'ownerId',
                        'ownerSiteId',
                        'fieldId',
                        'sortOrder'
                    ],
                    'safe',
                    'on' => [
                        ModelHelper::SCENARIO_DEFAULT
                    ]
                ]
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return parent::getFieldLayout() ?? $this->getField()->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        // If the field is translatable, than each individual block is tied to a single site, and thus aren't
        // translatable. Otherwise all elements belong to all sites, and their content is translatable.

        if ($this->ownerSiteId !== null) {
            return [$this->ownerSiteId];
        }

        $owner = $this->getOwner();

        if ($owner) {
            $siteIds = [];

            foreach (ElementHelper::supportedSitesForElement($owner) as $siteInfo) {
                $siteIds[] = $siteInfo['siteId'];
            }

            return $siteIds;
        }

        return [Craft::$app->getSites()->getPrimarySite()->id];
    }

    /**
     * @inheritdoc
     */
    public function getContentTable(): string
    {
        return FieldHelper::getContentTableName($this->fieldId);
    }

    /**
     * @inheritdoc
     */
    public function getFieldContext(): string
    {
        return FieldHelper::getContextById($this->fieldId);
    }

    /**
     * @inheritdoc
     */
    public function getHasFreshContent(): bool
    {
        $owner = $this->getOwner();
        return $owner ? $owner->getHasFreshContent() : false;
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function afterSave(bool $isNew)
    {
        MetaPlugin::getInstance()->getElements()->afterSave($this, $isNew);
        parent::afterSave($isNew);
    }

    /**
     * Returns the Meta field.
     *
     * @return MetaField
     */
    private function getField()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Craft::$app->getFields()->getFieldById($this->fieldId);
    }
}
