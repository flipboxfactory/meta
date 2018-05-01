<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-ember/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-ember
 */

namespace flipbox\meta\traits;

use craft\base\Element;
use flipbox\ember\helpers\ModelHelper;

/**
 * @property int|null $ownerId
 * @property Element|null $owner
 *
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
trait OwnerRules
{
    /**
     * @return array
     */
    protected function ownerRules(): array
    {
        return [
            [
                [
                    'ownerId'
                ],
                'number',
                'integerOnly' => true
            ],
            [
                [
                    'ownerId',
                    'owner'
                ],
                'safe',
                'on' => [
                    ModelHelper::SCENARIO_DEFAULT
                ]
            ]
        ];
    }
}
