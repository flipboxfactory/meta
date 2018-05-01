<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/organization/license
 * @link       https://www.flipboxfactory.com/software/organization/
 */

namespace flipbox\meta\db;

use craft\db\QueryAbortedException;
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
    use traits\Attributes;

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
     * @param $builder
     * @return $this|void|\yii\db\Query
     * @throws QueryAbortedException
     */
    public function prepare($builder)
    {
        if (($this->ownerId !== null && empty($this->ownerId)) ||
            ($this->id !== null && empty($this->id))
        ) {
            throw new QueryAbortedException();
        }

        $this->applyConditions($this);
    }
}
