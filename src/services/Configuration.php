<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\services;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\fields\Matrix;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\records\Field as FieldRecord;
use flipbox\meta\db\MetaQuery;
use flipbox\meta\elements\Meta;
use flipbox\meta\fields\Meta as MetaField;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\migrations\ContentTable;
use flipbox\meta\records\Meta as MetaRecord;
use flipbox\meta\web\assets\input\Input;
use flipbox\meta\web\assets\settings\Settings as MetaSettingsAsset;
use yii\base\Component;
use yii\base\Exception;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Configuration extends Component
{
    /**
     * @param MetaField $metaField
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function save(MetaField $metaField)
    {
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $contentService = Craft::$app->getContent();

            // Create/Rename table
            $this->ensureTable($metaField);

            // Get the originals
            $originalContentTable = $contentService->contentTable;
            $originalFieldContext = $contentService->fieldContext;
            $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;

            // Set our content table
            $contentService->contentTable = FieldHelper::getContentTableName($metaField->id);
            $contentService->fieldContext = FieldHelper::getContextById($metaField->id);
            $contentService->fieldColumnPrefix = 'field_';

            // Delete old fields
            $this->deleteOldFields($metaField);

            // Save fields
            $this->saveNewFields($metaField);

            // Revert to originals
            $contentService->contentTable = $originalContentTable;
            $contentService->fieldContext = $originalFieldContext;
            $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;

            // Save the fieldLayoutId via settings
            /** @var FieldRecord $fieldRecord */
            $fieldRecord = FieldRecord::findOne($metaField->id);
            $fieldRecord->settings = $metaField->getSettings();

            if (!$fieldRecord->save(true, ['settings'])) {
                $metaField->addError(
                    'settings',
                    Craft::t('meta', 'Unable to save settings.')
                );
                $transaction->rollback();
                return false;
            }
        } catch (\Exception $e) {
            $transaction->rollback();
            throw $e;
        }

        $transaction->commit();
        return true;
    }

    /**
     * @param MetaField $field
     * @return bool
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public function beforeDelete(MetaField $field)
    {
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {

            // First delete the elements
            $elements = Meta::find()
                ->fieldId($field->id)
                ->all();

            foreach ($elements as $element) {
                Craft::$app->getElements()->deleteElement($element);
            }

            /** @var FieldLayout $fieldLayout */
            $fieldLayout = $field->getFieldLayout();

            // Get content table name
            $contentTableName = FieldHelper::getContentTableName($field->id);

            $contentService = Craft::$app->getContent();

            // Get the originals
            $originalContentTable = $contentService->contentTable;
            $originalFieldContext = $contentService->fieldContext;
            $originalFieldColumnPrefix = $contentService->fieldColumnPrefix;

            // Set our content table
            $contentService->contentTable = $contentTableName;
            $contentService->fieldContext = FieldHelper::getContextById($field->id);
            $contentService->fieldColumnPrefix = 'field_';

            // Delete fields
            foreach ($fieldLayout->getFields() as $field) {
                Craft::$app->getFields()->deleteField($field);
            }

            // Revert to originals
            $contentService->contentTable = $originalContentTable;
            $contentService->fieldContext = $originalFieldContext;
            $contentService->fieldColumnPrefix = $originalFieldColumnPrefix;

            // Drop the content table
            Craft::$app->getDb()->createCommand()
                ->dropTableIfExists($contentTableName)
                ->execute();

            // Delete field layout
            Craft::$app->getFields()->deleteLayout($fieldLayout);

            $transaction->commit();

            return true;
        } catch (\Exception $e) {
            // Revert
            $transaction->rollback();

            throw $e;
        }
    }


    /**
     * @param MetaField $metaField
     * @return bool
     */
    public function validate(MetaField $metaField): bool
    {
        $validates = true;

        // Can't validate multiple new rows at once so we'll need to give these temporary context to avoid false unique
        // handle validation errors, and just validate those manually. Also apply the future fieldColumnPrefix so that
        // field handle validation takes its length into account.
        $contentService = Craft::$app->getContent();
        $originalFieldContext = $contentService->fieldContext;

        $contentService->fieldContext = StringHelper::randomString(10);

        /** @var FieldRecord $field */
        foreach ($metaField->getFieldLayout()->getFields() as $field) {
            // Hack to allow blank field names
            if (!$field->name) {
                $field->name = '__blank__';
            }

            if (!$field->validate()) {
                $metaField->hasFieldErrors = true;
                $validates = false;
            }
        }

        $contentService->fieldContext = $originalFieldContext;

        return $validates;
    }

    /**
     * @inheritdoc
     */
    private function createContentTable($tableName)
    {
        $migration = new ContentTable([
            'tableName' => $tableName
        ]);

        ob_start();
        $migration->up();
        ob_end_clean();
    }


    /*******************************************
     * HTML
     *******************************************/

    /**
     * @param MetaField $field
     * @param $value
     * @param ElementInterface|null $element
     * @return string
     * @throws Exception
     * @throws \Twig_Error_Loader
     * @throws \yii\base\InvalidConfigException
     */
    public function getInputHtml(MetaField $field, $value, ElementInterface $element = null): string
    {
        $id = Craft::$app->getView()->formatInputId($field->handle);

        // Get the field data
        $fieldInfo = $this->getFieldInfoForInput($field, $element);

        Craft::$app->getView()->registerAssetBundle(Input::class);

        Craft::$app->getView()->registerJs(
            'new Craft.MetaInput(' .
            '"' . Craft::$app->getView()->namespaceInputId($id) . '", ' .
            Json::encode($fieldInfo, JSON_UNESCAPED_UNICODE) . ', ' .
            '"' . Craft::$app->getView()->namespaceInputName($field->handle) . '", ' .
            ($field->min ?: 'null') . ', ' .
            ($field->max ?: 'null') .
            ');'
        );

        Craft::$app->getView()->registerTranslations('meta', [
            'Add new',
            'Add new above'
        ]);

        if ($value instanceof MetaQuery) {
            $value
                ->limit(null)
                ->status(null)
                ->enabledForSite(false);
        }

        return Craft::$app->getView()->renderTemplate(
            FieldHelper::TEMPLATE_PATH . '/input',
            [
                'id' => $id,
                'name' => $field->handle,
                'field' => $field,
                'elements' => $value->all(),
                'static' => false,
                'template' => $field::DEFAULT_TEMPLATE
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(MetaField $field)
    {
        // Get the available field types data
        $fieldTypeInfo = $this->getFieldOptionsForConfiguration();

        $view = Craft::$app->getView();

        $view->registerAssetBundle(MetaSettingsAsset::class);
        $view->registerJs(
            'new Craft.MetaConfiguration(' .
            Json::encode($fieldTypeInfo, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode(Craft::$app->getView()->getNamespace(), JSON_UNESCAPED_UNICODE) .
            ');'
        );

        $view->registerTranslations('meta', [
            'New field'
        ]);

        $fieldTypeOptions = [];

        /** @var Field|string $class */
        foreach (Craft::$app->getFields()->getAllFieldTypes() as $class) {
            $fieldTypeOptions[] = [
                'value' => $class,
                'label' => $class::displayName()
            ];
        }

//        // Handle missing fields
//        $fields = $field->getFields();
//        foreach ($fields as $i => $field) {
//            if ($field instanceof MissingField) {
//                $fields[$i] = $field->createFallback(PlainText::class);
//                $fields[$i]->addError('type', Craft::t('app', 'The field type “{type}” could not be found.', [
//                    'type' => $field->expectedType
//                ]));
//                $field->hasFieldErrors = true;
//            }
//        }
//        $field->setFields($fields);

        return Craft::$app->getView()->renderTemplate(
            FieldHelper::TEMPLATE_PATH . '/settings',
            [
                'field' => $field,
                'fieldTypes' => $fieldTypeOptions,
                'defaultTemplate' => $field::DEFAULT_TEMPLATE
            ]
        );
    }

    /**
     * TODO - eliminate this and render configuration via ajax call
     *
     * Returns html for all associated field types for the Meta field input.
     *
     * @return array
     */
    private function getFieldInfoForInput(MetaField $field, ElementInterface $element = null): array
    {
        // Set a temporary namespace for these
        $originalNamespace = Craft::$app->getView()->getNamespace();
        $namespace = Craft::$app->getView()->namespaceInputName(
            $field->handle . '[__META__][fields]',
            $originalNamespace
        );
        Craft::$app->getView()->setNamespace($namespace);

        // Create a fake meta so the field types have a way to get at the owner element, if there is one
        $meta = new Meta();
        $meta->fieldId = $field->id;

        if ($element) {
            $meta->setOwner($element);
            $meta->siteId = $element->siteId;
        }

        $fieldLayoutFields = $field->getFieldLayout()->getFields();

        // Set $_isFresh's
        foreach ($fieldLayoutFields as $field) {
            $field->setIsFresh(true);
        }

        Craft::$app->getView()->startJsBuffer();

        $bodyHtml = Craft::$app->getView()->namespaceInputs(
            Craft::$app->getView()->renderTemplate(
                '_includes/fields',
                [
                    'namespace' => null,
                    'fields' => $fieldLayoutFields,
                    'element' => $meta
                ]
            )
        );

        // Reset $_isFresh's
        foreach ($fieldLayoutFields as $field) {
            $field->setIsFresh(null);
        }

        $footHtml = Craft::$app->getView()->clearJsBuffer();

        $fields = [
            'bodyHtml' => $bodyHtml,
            'footHtml' => $footHtml,
        ];

        // Revert namespace
        Craft::$app->getView()->setNamespace($originalNamespace);

        return $fields;
    }

    /**
     *
     * TODO - eliminate this and render configuration via ajax call
     *
     * Returns info about each field type for the configurator.
     *
     * @return array
     */
    private function getFieldOptionsForConfiguration()
    {
        $disallowedFields = [
            MetaField::class,
            Matrix::class
        ];

        $fieldTypes = [];

        // Set a temporary namespace for these
        $originalNamespace = Craft::$app->getView()->getNamespace();
        $namespace = Craft::$app->getView()->namespaceInputName('fields[__META_FIELD__][settings]', $originalNamespace);
        Craft::$app->getView()->setNamespace($namespace);

        /** @var Field|string $class */
        foreach (Craft::$app->getFields()->getAllFieldTypes() as $class) {
            // Ignore disallowed fields
            if (in_array($class, $disallowedFields)) {
                continue;
            }

            Craft::$app->getView()->startJsBuffer();

            /** @var FieldInterface $field */
            $field = new $class();

            if ($settingsHtml = (string)$field->getSettingsHtml()) {
                $settingsHtml = Craft::$app->getView()->namespaceInputs($settingsHtml);
            }

            $settingsBodyHtml = $settingsHtml;
            $settingsFootHtml = Craft::$app->getView()->clearJsBuffer();

            $fieldTypes[] = [
                'type' => $class,
                'name' => $class::displayName(),
                'settingsBodyHtml' => $settingsBodyHtml,
                'settingsFootHtml' => $settingsFootHtml,
            ];
        }

        Craft::$app->getView()->setNamespace($originalNamespace);

        return $fieldTypes;
    }

    /**
     * @param MetaField $metaField
     * @throws Exception
     * @throws \Throwable
     */
    private function deleteOldFields(MetaField $metaField)
    {
        /** @var \craft\services\Fields $fieldsService */
        $fieldsService = Craft::$app->getFields();

        // Get existing fields
        $oldFields = $fieldsService->getAllFields(FieldHelper::getContextById($metaField->id));
        $oldFieldsById = ArrayHelper::index($oldFields, 'id');

        /** @var \craft\base\Field $field */
        foreach ($metaField->getFieldLayout()->getFields() as $field) {
            if (!$field->getIsNew()) {
                ArrayHelper::remove($oldFieldsById, $field->id);
            }
        }

        // Drop the old fields
        foreach ($oldFieldsById as $field) {
            if (!$fieldsService->deleteField($field)) {
                throw new Exception(Craft::t('app', 'An error occurred while deleting this Meta field.'));
            }
        }

        // Refresh the schema cache
        Craft::$app->getDb()->getSchema()->refresh();
    }

    /**
     * @param MetaField $metaField
     * @throws Exception
     * @throws \Throwable
     */
    private function saveNewFields(MetaField $metaField)
    {
        $fieldLayoutFields = [];
        $sortOrder = 0;

        /** @var \craft\services\Fields $fieldsService */
        $fieldsService = Craft::$app->getFields();

        // Save field
        /** @var \craft\base\Field $field */
        foreach ($metaField->getFieldLayout()->getFields() as $field) {
            // Save field (we validated earlier)
            if (!$fieldsService->saveField($field, false)) {
                throw new Exception('An error occurred while saving this Meta field.');
            }

            // Set sort order
            $field->sortOrder = ++$sortOrder;

            $fieldLayoutFields[] = $field;
        }

        $fieldLayout = $metaField->getFieldLayout();

        $fieldLayoutTab = new FieldLayoutTab([
            'name' => 'Fields',
            'sortOrder' => 1,
            'fields' => $fieldLayoutFields
        ]);

        $fieldLayout->setTabs([$fieldLayoutTab]);
        $fieldLayout->setFields($fieldLayoutFields);

        $fieldsService->saveLayout($fieldLayout);

        // Update the element & record with our new field layout ID
        $metaField->setFieldLayout($fieldLayout);
        $metaField->fieldLayoutId = (int)$fieldLayout->id;
    }

    /**
     * @param MetaField $metaField
     */
    private function ensureTable(MetaField $metaField)
    {
        // Create the content table first since the element fields will need it
        $contentTable = FieldHelper::getContentTableName($metaField->id);

        // Do we need to create/rename the content table?
        if (!Craft::$app->getDb()->tableExists($contentTable)) {
            $this->createContentTable($contentTable);
            Craft::$app->getDb()->getSchema()->refresh();
        }
    }
}
