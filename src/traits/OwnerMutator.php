<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://github.com/flipboxfactory/craft-ember/blob/master/LICENSE
 * @link       https://github.com/flipboxfactory/craft-ember
 */

namespace flipbox\meta\traits;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;

/**
 * @property int|null $ownerId
 * @property Element|ElementInterface|null $owner
 *
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
trait OwnerMutator
{
    /**
     * @var Element|null
     */
    private $owner;

    /**
     * Set associated ownerId
     *
     * @param $id
     * @return $this
     */
    public function setOwnerId(int $id = null)
    {
        $this->ownerId = $id;
        return $this;
    }

    /**
     * Get associated ownerId
     *
     * @return int|null
     */
    public function getOwnerId()
    {
        if (null === $this->ownerId && null !== $this->owner) {
            $this->ownerId = $this->owner->id;
        }

        return $this->ownerId;
    }

    /**
     * Associate a owner
     *
     * @param mixed $owner
     * @return $this
     */
    public function setOwner($owner = null)
    {
        $this->owner = null;

        if (!$owner = $this->internalResolveOwner($owner)) {
            $this->owner = $this->ownerId = null;
        } else {
            /** @var Element $owner */
            $this->ownerId = $owner->id;
            $this->owner = $owner;
        }

        return $this;
    }

    /**
     * @return ElementInterface|null
     */
    public function getOwner()
    {
        /** @var Element $owner */
        if ($this->owner === null) {
            $owner = $this->resolveOwner();
            $this->setOwner($owner);
            return $owner;
        }

        $ownerId = $this->ownerId;
        if ($ownerId !== null &&
            $ownerId !== $this->owner->id
        ) {
            $this->owner = null;
            return $this->getOwner();
        }

        return $this->owner;
    }

    /**
     * @return ElementInterface|null
     */
    protected function resolveOwner()
    {
        if ($model = $this->resolveOwnerFromId()) {
            return $model;
        }

        return null;
    }

    /**
     * @return ElementInterface|null
     */
    private function resolveOwnerFromId()
    {
        if (null === $this->ownerId) {
            return null;
        }

        return Craft::$app->getElements()->getElementById($this->ownerId);
    }

    /**
     * @param mixed $owner
     * @return ElementInterface|Element|null
     */
    protected function internalResolveOwner($owner = null)
    {
        if ($owner instanceof ElementInterface) {
            return $owner;
        }

        if (is_numeric($owner)) {
            return Craft::$app->getElements()->getElementById($owner);
        }

        if (is_string($owner)) {
            return Craft::$app->getElements()->getElementByUri($owner);
        }

        return Craft::$app->getElements()->createElement($owner);
    }
}
