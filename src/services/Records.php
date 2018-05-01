<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\services;

use flipbox\craft\sortable\associations\db\SortableAssociationQueryInterface;
use flipbox\craft\sortable\associations\records\SortableAssociationInterface;
use flipbox\craft\sortable\associations\services\SortableAssociations;
use flipbox\ember\services\traits\records\Accessor;
use flipbox\meta\db\MetaAssociationsQuery;
use flipbox\meta\db\MetaQuery;
use flipbox\meta\records\Meta as MetaRecord;
use yii\db\ActiveQuery;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @method MetaAssociationsQuery parentGetQuery($config = [])
 * @method MetaRecord create(array $attributes = [])
 * @method MetaRecord find($identifier)
 * @method MetaRecord get($identifier)
 * @method MetaRecord findByCondition($condition = [])
 * @method MetaRecord getByCondition($condition = [])
 * @method MetaRecord findByCriteria($criteria = [])
 * @method MetaRecord getByCriteria($criteria = [])
 * @method MetaRecord[] findAllByCondition($condition = [])
 * @method MetaRecord[] getAllByCondition($condition = [])
 * @method MetaRecord[] findAllByCriteria($criteria = [])
 * @method MetaRecord[] getAllByCriteria($criteria = [])
 */
class Records extends SortableAssociations
{
    use Accessor;

    /**
     * @inheritdoc
     */
    const SOURCE_ATTRIBUTE = MetaRecord::SOURCE_ATTRIBUTE;

    /**
     * @inheritdoc
     */
    const TARGET_ATTRIBUTE = MetaRecord::TARGET_ATTRIBUTE;

    /**
     * @inheritdoc
     */
    public static function recordClass(): string
    {
        return MetaRecord::class;
    }

    /**
     * @inheritdoc
     */
    protected static function tableAlias(): string
    {
        return MetaRecord::tableAlias();
    }

    /**
     * @inheritdoc
     * @return MetaQuery
     */
    public function getQuery($config = []): SortableAssociationQueryInterface
    {
        return new MetaAssociationsQuery(static::recordClass(), $config);
    }

    /**
     * @inheritdoc
     *
     * @param MetaRecord $record
     * @return MetaQuery
     */
    protected function associationQuery(
        SortableAssociationInterface $record
    ): SortableAssociationQueryInterface {
        return $this->query(
            $record->{static::SOURCE_ATTRIBUTE},
            $record->fieldId,
            $record->ownerSiteId
        );
    }

    /**
     * @inheritdoc
     *
     * @param MetaQuery $query
     * @return array
     */
    protected function existingAssociations(
        SortableAssociationQueryInterface $query
    ): array {
        $source = $this->resolveStringAttribute($query, static::SOURCE_ATTRIBUTE);
        $field = $this->resolveStringAttribute($query, 'fieldId');
        $site = $this->resolveStringAttribute($query, 'siteId');

        if ($source === null || $field === null || $site === null) {
            return [];
        }

        return $this->associations($source, $field, $site);
    }

    /**
     * @param $source
     * @param int $fieldId
     * @param int $siteId
     * @return SortableAssociationQueryInterface|ActiveQuery
     */
    private function query(
        $source,
        int $fieldId,
        int $siteId = null
    ): SortableAssociationQueryInterface {
        return $this->getQuery()
            ->where([
                static::SOURCE_ATTRIBUTE => $source,
                'fieldId' => $fieldId,
                'siteId' => $siteId
            ])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }

    /**
     * @param $source
     * @param int $fieldId
     * @param int $siteId
     * @return array
     */
    private function associations(
        $source,
        int $fieldId,
        int $siteId
    ): array {
        return $this->query($source, $fieldId, $siteId)
            ->indexBy(static::TARGET_ATTRIBUTE)
            ->all();
    }

//    /**
//     * @param SortableAssociationQueryInterface $query
//     * @return \flipbox\domains\fields\Domains|null
//     */
//    protected function resolveFieldFromQuery(
//        SortableAssociationQueryInterface $query
//    ) {
//        if (null === ($fieldId = $this->resolveStringAttribute($query, 'fieldId'))) {
//            return null;
//        }
//
//        return MetaPlugin::getInstance()->getFields()->findById($fieldId);
//    }
//
//    /**
//     * @inheritdoc
//     * @param bool $validate
//     * @throws \Exception
//     */
//    public function save(
//        SortableAssociationQueryInterface $query,
//        bool $validate = true
//    ): bool {
//        if ($validate === true && null !== ($field = $this->resolveFieldFromQuery($query))) {
//            $error = '';
//
//            (new MinMaxValidator([
//                'min' => $field->min,
//                'max' => $field->max
//            ]))->validate($query, $error);
//
//            if (!empty($error)) {
//                MetaPlugin::error(sprintf(
//                    "Meta failed to save due to the following validation errors: '%s'",
//                    Json::encode($error)
//                ));
//                return false;
//            }
//        }
//
//        return parent::save($query);
//    }
}