<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\migrations;

use craft\db\Migration;
use craft\records\Element;
use craft\records\Site;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class ContentTable extends Migration
{
    /**
     *  The table name
     *
     * @var string|null
     */
    public $tableName;

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTable(
            $this->tableName,
            [
                'id' => $this->primaryKey(),
                'elementId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]
        );

        $this->createIndex(
            null,
            $this->tableName,
            ['elementId', 'siteId'],
            true
        );

        $this->addForeignKey(
            null,
            $this->tableName,
            ['elementId'],
            Element::tableName(),
            ['id'],
            'CASCADE',
            null
        );

        $this->addForeignKey(
            null,
            $this->tableName,
            ['siteId'],
            Site::tableName(),
            ['id'],
            'CASCADE',
            'CASCADE'
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        return false;
    }
}
