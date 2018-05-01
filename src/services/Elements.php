<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\services;

use flipbox\ember\helpers\ArrayHelper;
use flipbox\ember\helpers\SiteHelper;
use flipbox\ember\services\traits\elements\MultiSiteAccessor;
use flipbox\meta\db\MetaQuery;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\Meta as MetaPlugin;
use yii\base\Component;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @method MetaElement create($config = [])
 * @method MetaElement find($identifier, int $siteId = null)
 * @method MetaElement get($identifier, int $siteId = null)
 * @method MetaQuery getQuery($criteria = [])
 */
class Elements extends Component
{
    use MultiSiteAccessor;

    /**
     * @inheritdoc
     */
    public static function elementClass(): string
    {
        return MetaElement::class;
    }

    /**
     * @param $identifier
     * @param int|null $siteId
     * @return array
     */
    protected function identifierCondition($identifier, int $siteId = null): array
    {
        $base = [
            'siteId' => SiteHelper::ensureSiteId($siteId),
            'status' => null
        ];

        if (is_array($identifier)) {
            return array_merge($base, $identifier);
        }

        if (!is_numeric($identifier) && is_string($identifier)) {
            $base['handle'] = $identifier;
        } else {
            $base['id'] = $identifier;
        }

        return $base;
    }

    /**
     * @param mixed $organization
     * @return MetaElement
     */
    public function resolve($organization): MetaElement
    {
        if (is_array($organization) &&
            null !== ($id = ArrayHelper::getValue($organization, 'id'))
        ) {
            return $this->get($id);
        }

        if ($object = $this->find($organization)) {
            return $object;
        }

        return $this->create($organization);
    }

    /**
     * Perform the business logic after an element has been saved.
     *
     * @param MetaElement $meta
     * @param bool $isNew
     */
    public function afterSave(MetaElement $meta, bool $isNew)
    {
        $associationService = MetaPlugin::getInstance()->getRecords();

        if (!$isNew) {
            $record = $associationService->get($meta->getId());
        } else {
            $record = $associationService->create([
                'id' => $meta->getId()
            ]);
        }

        $record->fieldId = $meta->fieldId;
        $record->ownerId = $meta->getOwnerId();
        $record->ownerSiteId = $meta->ownerSiteId;
        $record->sortOrder = $meta->sortOrder;
        $record->associate();
    }
}
