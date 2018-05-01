<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\records;

use craft\records\Element;
use craft\records\Field;
use craft\records\Site;
use craft\validators\SiteIdValidator;
use flipbox\craft\sortable\associations\records\SortableAssociationInterface;
use flipbox\ember\helpers\ModelHelper;
use flipbox\ember\records\ActiveRecordWithId;
use flipbox\meta\Meta as MetaPlugin;
use yii\db\ActiveQueryInterface;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @property int $id ID
 * @property int $ownerId Owner ID
 * @property int $ownerSiteId Owner Site ID
 * @property int $fieldId Field ID
 * @property int $sortOrder Sort order
 * @property Element $element Element
 * @property Element $owner Owner
 * @property Site $ownerSite Site
 * @property Field $field Field
 */
class Meta extends ActiveRecordWithId implements SortableAssociationInterface
{
    /**
     * The table alias
     */
    const TABLE_ALIAS = 'meta';

    /**
     * @inheritdoc
     */
    const TARGET_ATTRIBUTE = 'id';

    /**
     * @inheritdoc
     */
    const SOURCE_ATTRIBUTE = 'ownerId';

    /**
     * @inheritdoc
     */
    public function associate(bool $autoReorder = true): bool
    {
        return MetaPlugin::getInstance()->getRecords()->associate($this, $autoReorder);
    }

    /**
     * @inheritdoc
     */
    public function dissociate(bool $autoReorder = true): bool
    {
        return MetaPlugin::getInstance()->getRecords()->dissociate($this, $autoReorder);
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
     * Returns the meta element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * Returns the meta element’s owner.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getOwner(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'ownerId']);
    }

    /**
     * Returns the meta element’s owner's site.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getOwnerSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'ownerSiteId']);
    }

    /**
     * Returns the meta element’s field.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getField(): ActiveQueryInterface
    {
        return $this->hasOne(Field::class, ['id' => 'fieldId']);
    }
}
