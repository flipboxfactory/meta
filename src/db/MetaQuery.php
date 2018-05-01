<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\db;

use Craft;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\models\Site;
use flipbox\craft\sortable\associations\db\SortableAssociationQueryInterface;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\fields\Meta as MetaField;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\records\Meta as MetaRecord;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @property string|string[]|Site $ownerSite The handle(s) of the site(s) that the owner element should be in
 *
 * @method MetaElement[]|array all($db = null)
 * @method MetaElement|null one($db = null)
 */
class MetaQuery extends ElementQuery implements SortableAssociationQueryInterface
{
    use traits\Attributes;

    /**
     * @inheritdoc
     */
    public $orderBy = 'meta.sortOrder';

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // If we don't have an owner, we won't have any results
        if (($this->ownerId !== null && empty($this->ownerId)) ||
            ($this->id !== null && empty($this->id))
        ) {
            return false;
        }

        $this->joinTables();

        $this->query->select([
            MetaRecord::tableAlias() . '.fieldId',
            MetaRecord::tableAlias() . '.ownerId',
            MetaRecord::tableAlias() . '.ownerSiteId',
            MetaRecord::tableAlias() . '.sortOrder',
        ]);

        $this->applyConditions($this->subQuery);

        return parent::beforePrepare();
    }

    /**
     * Sets the [[ownerSiteId]] and [[siteId]] properties.
     *
     * @param int|string|null $value The property value
     * @return static self reference
     */
    public function ownerSiteId($value)
    {
        $this->ownerSiteId = $value;

        if ($value && strtolower($value) !== ':empty:') {
            // A block will never exist in a site that is different than its ownerSiteId,
            // so let's set the siteId param here too.
            $this->siteId = (int)$value;
        }

        return $this;
    }

    /**
     * Join element/content tables
     */
    private function joinTables()
    {
        $this->joinElementTable(MetaRecord::tableAlias());

        // Figure out which content table to use
        $this->contentTable = null;

        if (!$this->fieldId && $this->id && is_numeric($this->id)) {
            $this->fieldId = (new Query())
                ->select('fieldId')
                ->from(MetaRecord::tableName())
                ->where(['id' => $this->id])
                ->scalar();
        }

        if ($this->fieldId && is_numeric($this->fieldId)) {
            /** @var MetaField $field */
            $field = Craft::$app->getFields()->getFieldById($this->fieldId);

            if ($field) {
                $this->contentTable = FieldHelper::getContentTableName($field->id);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function customFields(): array
    {
        return Craft::$app->getFields()->getAllFields(
            FieldHelper::getContextById($this->fieldId)
        );
    }
}
