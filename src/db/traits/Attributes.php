<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://www.flipboxfactory.com/software/element-lists/license
 * @link       https://www.flipboxfactory.com/software/element-lists/
 */

namespace flipbox\meta\db\traits;

use craft\helpers\Db;
use yii\base\Exception;
use yii\db\Expression;
use yii\db\QueryInterface;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
trait Attributes
{


    /**
     * @var int|int[]|false|null The field ID(s). Prefix IDs with "not " to exclude them.
     */
    public $fieldId;

    /**
     * @var int|int[]|false|null The owner Id(s). Prefix Ids with "not " to exclude them.
     */
    public $ownerId;

    /**
     * @var int|int[]|false|null The owner site ID(s). Prefix IDs with "not " to exclude them.
     */
    public $ownerSiteId;

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the `AND` operator.
     * @param string|array|Expression $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @param array $params the parameters (name => value) to be bound to the query.
     * @return $this the query object itself
     * @see where()
     * @see orWhere()
     */
    abstract public function andWhere($condition, $params = []);

    /**
     * @param $value
     * @return static
     */
    public function fieldId($value)
    {
        $this->fieldId = $value;
        return $this;
    }

    /**
     * @param $value
     * @return static
     */
    public function field($value)
    {
        return $this->fieldId($value);
    }

    /**
     * @inheritdoc
     * @throws Exception if $value is an invalid site handle
     * return static
     */
    public function owner($value)
    {
        return $this->ownerId($value);
    }

    /**
     * @inheritdoc
     * return static
     */
    public function ownerId($value)
    {
        $this->ownerId = $value;
        return $this;
    }

    /**
     * @inheritdoc
     * return static
     */
    public function ownerSite($value)
    {
        return $this->ownerSiteId($value);
    }

    /**
     * @inheritdoc
     * return static
     */
    public function ownerSiteId($value)
    {
        $this->ownerSiteId = $value;
        return $this;
    }

    /**
     * @param QueryInterface $query
     */
    protected function applyConditions(QueryInterface $query)
    {
        if ($this->fieldId !== null) {
            $query->andWhere(Db::parseParam('fieldId', $this->fieldId));
        }

        if ($this->ownerId !== null) {
            $query->andWhere(Db::parseParam('ownerId', $this->ownerId));
        }

        if ($this->ownerSiteId !== null) {
            $query->andWhere(Db::parseParam('ownerSiteId', $this->ownerSiteId));
        }
    }
}
