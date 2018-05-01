<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/organization/license
 * @link       https://www.flipboxfactory.com/software/organization/
 */

namespace flipbox\meta\db;

use flipbox\craft\sortable\associations\db\SortableAssociationQuery;
use flipbox\meta\records\Meta as MetaRecord;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @method MetaRecord one($db = null)
 * @method MetaRecord[] all($db = null)
 * @method MetaRecord[] getCachedResult($db = null)
 */
class MetaAssociationsQuery extends SortableAssociationQuery
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->from === null) {
            $this->from([
                MetaRecord::tableName() . ' ' . MetaRecord::tableAlias()
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    protected function fixedOrderColumn(): string
    {
        return 'id';
    }

    /**
     * Apply conditions
     */
    protected function applyConditions()
    {
//        if ($this->typeId !== null) {
//            $this->andWhere(Db::parseParam('typeId', $this->typeId));
//        }
//
//        if ($this->organizationId !== null) {
//            $this->andWhere(Db::parseParam('organizationId', $this->organizationId));
//        }
    }
}
