<?php

namespace Drush\Drupal\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * This class is a good example of how to build a sql-sanitize plugin.
 */
class SanitizeUserFieldsCommands extends DrushCommands implements SanitizePluginInterface
{
    protected $database;
    protected $entityFieldManager;
    protected $entityTypeManager;

    public function __construct($database, $entityFieldManager, $entityTypeManager)
    {
        $this->database = $database;
        $this->entityFieldManager = $entityFieldManager;
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return mixed
     */
    public function getEntityFieldManager()
    {
        return $this->entityFieldManager;
    }

    /**
     * Sanitize string fields associated with the user.
     *
     * @todo Use Drupal services to get field info.
     *
     * @hook post-command sql-sanitize
     *
     * @inheritdoc
     */
    public function sanitize($result, CommandData $commandData): void
    {
        $options = $commandData->options();
        $conn = $this->getDatabase();
        $field_definitions = $this->getEntityFieldManager()->getFieldDefinitions('user', 'user');
        $field_storage = $this->getEntityFieldManager()->getFieldStorageDefinitions('user');
        /** @deprecated Use $options['allowlist-fields'] instead. */
        foreach (explode(',', $options['whitelist-fields']) as $key) {
            unset($field_definitions[$key], $field_storage[$key]);
        }
        foreach (explode(',', $options['allowlist-fields']) as $key) {
            unset($field_definitions[$key], $field_storage[$key]);
        }

        foreach ($field_definitions as $key => $def) {
            $execute = false;
            if (!isset($field_storage[$key]) || $field_storage[$key]->isBaseField()) {
                continue;
            }

            $table = 'user__' . $key;
            $query = $conn->update($table);
            $name = $def->getName();
            $field_type_class = \Drupal::service('plugin.manager.field.field_type')->getPluginClass($def->getType());
            $supported_field_types = ['email', 'string', 'string_long', 'telephone', 'text', 'text_long', 'text_with_summary'];
            if (in_array($def->getType(), $supported_field_types)) {
                $value_array = $field_type_class::generateSampleValue($def);
                $value = $value_array['value'];
            }
            switch ($def->getType()) {
                case 'email':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;
                case 'string':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'string_long':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'telephone':
                    $query->fields([$name . '_value' => '15555555555']);
                    $execute = true;
                    break;

                case 'text':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'text_long':
                    $query->fields([$name . '_value' => $value]);
                    $execute = true;
                    break;

                case 'text_with_summary':
                    $query->fields([
                    $name . '_value' => $value,
                    $name . '_summary' => $value_array['summary'],
                    ]);
                    $execute = true;
                    break;
            }
            if ($execute) {
                $query->execute();
                $this->entityTypeManager->getStorage('user')->resetCache();
                $this->logger()->success(dt('!table table sanitized.', ['!table' => $table]));
            } else {
                $this->logger()->success(dt('No text fields for users need sanitizing.', ['!table' => $table]));
            }
        }
    }

    /**
     * @hook on-event sql-sanitize-confirms
     *
     * @inheritdoc
     */
    public function messages(&$messages, InputInterface $input): void
    {
        $messages[] = dt('Sanitize text fields associated with users.');
    }

    /**
     * @hook option sql-sanitize
     * @option whitelist-fields Deprecated. Use allowlist-fields instead.
     * @option allowlist-fields A comma delimited list of fields exempt from sanitization.
     */
    public function options($options = ['whitelist-fields' => '', 'allowlist-fields' => '']): void
    {
    }
}
