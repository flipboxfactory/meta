<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\services;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\db\ElementQueryInterface;
use craft\fields\BaseRelationField;
use flipbox\craft\sortable\associations\db\SortableAssociationQueryInterface;
use flipbox\craft\sortable\associations\records\SortableAssociationInterface;
use flipbox\craft\sortable\associations\services\SortableFields;
use flipbox\meta\db\MetaQuery;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\fields\Meta;
use flipbox\meta\fields\Meta as MetaField;
use flipbox\meta\Meta as MetaPlugin;
use flipbox\meta\records\Meta as MetaRecord;
use yii\base\Exception;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @method MetaQuery find()
 */
class Fields extends SortableFields
{
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
    protected static function tableAlias(): string
    {
        return MetaRecord::tableAlias();
    }

    /**
     * @param FieldInterface $field
     * @throws Exception
     */
    private function ensureField(FieldInterface $field)
    {
        if (!$field instanceof MetaField) {
            throw new Exception(sprintf(
                "The field must be an instance of '%s', '%s' given.",
                (string)MetaField::class,
                (string)get_class($field)
            ));
        }
    }

    /**
     * @inheritdoc
     */
    public function getQuery(
        FieldInterface $field,
        ElementInterface $element = null
    ): SortableAssociationQueryInterface {
        /** @var MetaField $field */
        $this->ensureField($field);

        $query = MetaPlugin::getInstance()->getElements()->getQuery();

        $query->siteId = $this->targetSiteId($element);
        $query->fieldId = $field->id;

        return $query;
    }


    /*******************************************
     * NORMALIZE VALUE
     *******************************************/

    /**
     * @param FieldInterface $field
     * @param $value
     * @param ElementInterface|null $element
     * @return array
     */
    public function serializeValue(
        FieldInterface $field,
        $value,
        ElementInterface $element = null
    ): array {
        /** @var MetaQuery $value */
        $serialized = [];
        $new = 0;

        foreach ($value->all() as $meta) {
            $metaId = $meta->id ?? 'new' . ++$new;
            $serialized[$metaId] = [
                'enabled' => $meta->enabled,
                'fields' => $meta->getSerializedFieldValues(),
            ];
        }

        return $serialized;
    }

    /*******************************************
     * NORMALIZE VALUE
     *******************************************/

    /**
     * Accepts input data and converts it into an array of associated Meta elements
     *
     * @param FieldInterface $field
     * @param SortableAssociationQueryInterface $query
     * @param array $value
     * @param ElementInterface|null $element
     */
    protected function normalizeQueryInputValues(
        FieldInterface $field,
        SortableAssociationQueryInterface $query,
        array $value,
        ElementInterface $element = null
    ) {
        /** @var MetaField $field */

        $models = [];
        $sortOrder = 1;
        $prevElement = null;
        /** @var MetaElement|null $prevElement */

        // Get existing values
        $existingValues = $element === null ? [] : $this->getExistingValues($field, $value, $element);
        $ownerId = $element->id ?? null;

        foreach ($value as $metaId => $metaData) {
            // Is this new? (Or has it been deleted?)
            if (strpos($metaId, 'new') === 0 || !isset($existingValues[$metaId])) {
                $meta = MetaPlugin::getInstance()->getElements()->create([
                    'fieldId' => $field->id,
                    'ownerId' => $ownerId,
                    'siteId' => $this->targetSiteId($element)
                ]);
            } else {
                $meta = $existingValues[$metaId];
            }

            /** @var MetaElement $meta */

            $meta->enabled = (bool)$metaData['enabled'] ?? true;
            $meta->setOwnerId($ownerId);

            // Set the content post location on the element if we can
            $fieldNamespace = $element->getFieldParamNamespace();

            if ($fieldNamespace !== null) {
                $metaFieldNamespace = ($fieldNamespace ? $fieldNamespace . '.' : '') .
                    '.' . $field->handle .
                    '.' . $metaId .
                    '.fields';
                $meta->setFieldParamNamespace($metaFieldNamespace);
            }

            if (isset($metaData['fields'])) {
                $meta->setFieldValues($metaData['fields']);
            }

            $sortOrder++;
            $meta->sortOrder = $sortOrder;

            // Set the prev/next elements
            if ($prevElement) {
                $prevElement->setNext($meta);
                $meta->setPrev($prevElement);
            }
            $prevElement = $meta;

            $models[] = $meta;
        }
        $query->setCachedResult($models);
    }

    /**
     * @param array $values
     * @param ElementInterface $element
     * @return array
     */
    protected function getExistingValues(MetaField $field, array $values, ElementInterface $element): array
    {
        /** @var Element $element */
        if (!empty($element->id)) {
            $ids = [];

            foreach ($values as $metaId => $meta) {
                if (is_numeric($metaId) && $metaId !== 0) {
                    $ids[] = $metaId;
                }
            }

            if (!empty($ids)) {
                $oldMetaQuery = MetaPlugin::getInstance()->getElements()->getQuery();
                $oldMetaQuery->fieldId($field->id);
                $oldMetaQuery->ownerId($element->id);
                $oldMetaQuery->id($ids);
                $oldMetaQuery->limit(null);
                $oldMetaQuery->status(null);
                $oldMetaQuery->enabledForSite(false);
                $oldMetaQuery->siteId($element->siteId);
                $oldMetaQuery->indexBy('id');
                return $oldMetaQuery->all();
            }
        }

        return [];
    }


    /*******************************************
     * ELEMENT EVENTS
     *******************************************/

    /**
     * @param MetaField $field
     * @param ElementInterface $element
     * @return bool
     * @throws \Throwable
     */
    public function beforeElementDelete(MetaField $field, ElementInterface $element): bool
    {
        // Delete any meta elements that belong to this element(s)
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            $query = MetaElement::find();
            $query->status(null);
            $query->enabledForSite(false);
            $query->fieldId($field->id);
            $query->siteId($siteId);
            $query->owner($element);

            /** @var MetaElement $meta */
            foreach ($query->all() as $meta) {
                Craft::$app->getElements()->deleteElement($meta);
            }
        }

        return true;
    }

    /**
     * @param Meta $field
     * @param ElementInterface $owner
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function afterElementSave(MetaField $field, ElementInterface $owner)
    {
        /** @var Element $owner */

        /** @var MetaQuery $query */
        $query = $owner->getFieldValue($field->handle);

        // Skip if the query's site ID is different than the element's
        // (Indicates that the value as copied from another site for element propagation)
        if ($query->siteId != $owner->siteId) {
            return;
        }

        if (null === ($elements = $query->getCachedResult())) {
            $query = clone $query;
            $query->status = null;
            $query->enabledForSite = false;
            $elements = $query->all(); // existing meta
        }


        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // If this is a preexisting element, make sure that the blocks for this field/owner respect the field's translation setting
            if ($query->ownerId) {
                $this->applyFieldTranslationSetting($query->ownerId, $query->siteId, $field);
            }

            // If the query is set to fetch blocks of a different owner, we're probably duplicating an element
            if ($query->ownerId && $query->ownerId != $owner->id) {
                // Make sure this owner doesn't already have meta
                $newQuery = clone $query;
                $newQuery->ownerId = $owner->id;
                if (!$newQuery->exists()) {
                    // Duplicate the blocks for the new owner
                    $elementsService = Craft::$app->getElements();
                    foreach ($elements as $element) {
                        $elementsService->duplicateElement($element, [
                            'ownerId' => $owner->id,
                            'ownerSiteId' => $field->localize ? $owner->siteId : null
                        ]);
                    }
                }
            } else {
                $elementIds = [];

                // Only propagate the blocks if the owner isn't being propagated
                $propagate = !$owner->propagating;

                /** @var MetaElement $element */
                foreach ($elements as $element) {
                    $element->setOwnerId($owner->id);
                    $element->ownerSiteId = ($field->localize ? $owner->siteId : null);
                    $element->propagating = $owner->propagating;

                    Craft::$app->getElements()->saveElement($element, false, $propagate);

                    $elementIds[] = $element->id;
                }

                // Delete any elements that shouldn't be there anymore
                $deleteElementsQuery = MetaElement::find()
                    ->status(null)
                    ->enabledForSite(false)
                    ->ownerId($owner->id)
                    ->fieldId($field->id)
                    ->where(['not', ['elements.id' => $elementIds]]);

                if ($field->localize) {
                    $deleteElementsQuery->ownerSiteId($owner->siteId);
                } else {
                    $deleteElementsQuery->siteId($owner->siteId);
                }

                foreach ($deleteElementsQuery->all() as $deleteElement) {
                    Craft::$app->getElements()->deleteElement($deleteElement);
                }
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }

        return;
    }

    /**
     * Applies the field's translation setting to a set of blocks.
     *
     * @param int $ownerId
     * @param int $ownerSiteId
     * @param Meta $field
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     */
    private function applyFieldTranslationSetting(int $ownerId, int $ownerSiteId, MetaField $field)
    {
        // If the field is translatable, see if there are any global blocks that should be localized
        if ($field->localize) {
            $elementQuery = MetaElement::find()
                ->fieldId($field->id)
                ->ownerId($ownerId)
                ->status(null)
                ->enabledForSite(false)
                ->limit(null)
                ->siteId($ownerSiteId)
                ->ownerSiteId(':empty:');

            $elements = $elementQuery->all();

            if (!empty($elements)) {
                // Find any relational fields on these blocks
                $relationFields = [];

                /** @var MetaElement $element */
                foreach ($elements as $element) {
                    foreach ($element->getFieldLayout()->getFields() as $field) {
                        if ($field instanceof BaseRelationField) {
                            $relationFields[] = $field->handle;
                        }
                    }
                    break;
                }

                // Prefetch the blocks in all the other sites, in case they have
                // any localized content
                $otherSiteBlocks = [];
                $allSiteIds = Craft::$app->getSites()->getAllSiteIds();
                foreach ($allSiteIds as $siteId) {
                    if ($siteId != $ownerSiteId) {
                        /** @var MetaElement[] $siteElements */
                        $siteElements = $otherSiteBlocks[$siteId] = $elementQuery->siteId($siteId)->all();

                        // Hard-set the relation IDs
                        foreach ($siteElements as $element) {
                            foreach ($relationFields as $handle) {
                                /** @var ElementQueryInterface $relationQuery */
                                $relationQuery = $element->getFieldValue($handle);
                                $element->setFieldValue($handle, $relationQuery->ids());
                            }
                        }
                    }
                }

                // Explicitly assign the current site's blocks to the current site
                foreach ($elements as $element) {
                    $element->ownerSiteId = $ownerSiteId;
                    Craft::$app->getElements()->saveElement($element, false);
                }

                // Now save the other sites' blocks as new site-specific blocks
                foreach ($otherSiteBlocks as $siteId => $siteElements) {
                    foreach ($siteElements as $element) {
                        $element->id = null;
                        $element->contentId = null;
                        $element->siteId = (int)$siteId;
                        $element->ownerSiteId = (int)$siteId;
                        Craft::$app->getElements()->saveElement($element, false);
                    }
                }
            }
        } else {

            // Otherwise, see if the field has any localized blocks that should be deleted
            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                if ($siteId != $ownerSiteId) {
                    $elements = MetaElement::find()
                        ->fieldId($field->id)
                        ->ownerId($ownerId)
                        ->status(null)
                        ->enabledForSite(false)
                        ->limit(null)
                        ->siteId($siteId)
                        ->ownerSiteId($siteId)
                        ->all();

                    foreach ($elements as $element) {
                        Craft::$app->getElements()->deleteElement($element);
                    }
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function normalizeQueryInputValue(
        FieldInterface $field,
        $value,
        int &$sortOrder,
        ElementInterface $element = null
    ): SortableAssociationInterface {

        throw new Exception(__METHOD__ . ' is not implemented');
    }
}
