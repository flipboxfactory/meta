<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\helpers;

use Craft;
use craft\db\Query;
use craft\helpers\StringHelper;
use flipbox\meta\fields\Meta;
use flipbox\meta\records\Meta as MetaRecord;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Field
{

    const TEMPLATE_PATH = 'meta' .
    DIRECTORY_SEPARATOR . '_components' .
    DIRECTORY_SEPARATOR . 'fieldtypes' .
    DIRECTORY_SEPARATOR . 'Meta';

    /**
     * @var
     */
    private static $parenFields = [];

    /**
     * @param $fieldId
     * @return string
     */
    public static function getContextById($fieldId)
    {
        return self::getContextPrefix() . $fieldId;
    }

    /**
     * @return string
     */
    public static function getContextPrefix()
    {
        return MetaRecord::tableAlias() . ':';
    }

    /**
     * @param int $id
     * @return string
     */
    public static function getContentTableName(int $id)
    {
        return '{{%' . static::getContentTableAlias($id) . '}}';
    }

    /**
     * Returns the content table name for a given field.
     *
     * @param Meta $field
     * @param bool $useOldHandle Whether the method should use the field’s old handle when determining the table
     * name (e.g. to get the existing table name, rather than the new one).
     * @return string|false The table name, or `false` if $useOldHandle was set to `true` and there was no old handle.
     */
    public static function zgetContentTableAlias(Meta $field, bool $useOldHandle = false)
    {
        $name = '';

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        do {
            if ($useOldHandle) {
                if (!$field->oldHandle) {
                    return false;
                }

                $handle = $field->oldHandle;
            } else {
                $handle = $field->handle;
            }

            $name = '_' . StringHelper::toLowerCase($handle) . $name;
        } while ($matrixField = static::getParentField($field));

        return MetaRecord::tableAlias() . 'content_' . $name;
    }


    /**
     * Returns the parent Meta field, if any.
     *
     * @param Meta $field
     * @return Meta|null The Meta field’s parent Meta field, or `null` if there is none.
     */
    public static function getParentField(Meta $field)
    {
        if (array_key_exists($field->id, self::$parenFields)) {
            return self::$parenFields[$field->id];
        }

        // Does this Meta field belong to another one?
        $parentMatrixFieldId = (new Query())
            ->select(['fields.id'])
            ->from(['{{%fields}} fields'])
            ->innerJoin('{{%fieldlayoutfields}} fieldlayoutfields', '[[fieldlayoutfields.layoutId]] = :fieldLayoutId',
                [':fieldLayoutId', $field->getFieldLayout()->id ?? 0])
            ->where(['fieldlayoutfields.fieldId' => $field->id])
            ->scalar();

        if (!$parentMatrixFieldId) {
            return self::$parenFields[$field->id] = null;
        }

        return self::$parenFields[$field->id] = Craft::$app->getFields()->getFieldById($parentMatrixFieldId);;
    }

    /**
     * @param int $id
     * @return string
     */
    public static function getContentTableAlias(int $id)
    {
        return MetaRecord::tableAlias() . 'content_' . $id;
    }
}
