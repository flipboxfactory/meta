<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-ember/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-ember
 */

namespace flipbox\meta\traits;

use Craft;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
trait OwnerAttribute
{
    use OwnerRules, OwnerMutator;

    /**
     * @var int|null
     */
    private $ownerId;

    /**
     * @return array
     */
    protected function ownerFields(): array
    {
        return [
            'ownerId'
        ];
    }

    /**
     * @return array
     */
    protected function ownerAttributes(): array
    {
        return [
            'ownerId'
        ];
    }

    /**
     * @return array
     */
    protected function ownerAttributeLabels(): array
    {
        return [
            'ownerId' => Craft::t('meta', 'Owner Id')
        ];
    }
}
