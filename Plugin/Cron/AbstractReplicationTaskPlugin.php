<?php

namespace Ls\Hospitality\Plugin\Cron;

use \Ls\Replication\Cron\AbstractReplicationTask;
use \Ls\Replication\Helper\ReplicationHelper;

/**
 * Interceptor to intercept AbstractReplicationTask
 */
class AbstractReplicationTaskPlugin
{
    /**
     * Handle repl_item_modifier saves.
     *
     * This job needs custom existence matching and a cascade soft-delete (a single delete message
     * can soft-delete several flat rows matched by a partial key) that the batched
     * INSERT ... ON DUPLICATE KEY UPDATE write path in the core task cannot express. For it we
     * therefore bypass the batch buffer (do not call $proceed) and persist via the ORM path, then
     * apply the modifier-specific logic below. Every other config falls through to the core task
     * unchanged (which may batch or use the ORM path per its own rules).
     *
     * @param AbstractReplicationTask $subject
     * @param callable $proceed
     * @param array $properties
     * @param mixed $source
     * @return mixed
     */
    public function aroundSaveSource(AbstractReplicationTask $subject, callable $proceed, $properties, $source)
    {
        $configPath = $subject->getConfigPath();

        if ($configPath != "ls_mag/replication/repl_item_modifier") {
            return $proceed($properties, $source);
        }

        // Apply the config-specific source formatting (item-modifier enum decode) that the core
        // dispatcher normally runs before its batch/ORM split, then do a generic ORM save (sets
        // identity_value/checksum, no batching). Together these mirror the original pre-batch flow
        // where the vendor saveSource ran before this plugin's corrective logic.
        $subject->formatSourceColumns($source, $configPath);
        $subject->saveSourceOrm($properties, $source);

        if ($source->getIsDeleted()) {
            $uniqueAttributes = (array_key_exists(
                $subject->getConfigPath(),
                ReplicationHelper::DELETE_JOB_CODE_UNIQUE_FIELD_ARRAY
            )) ?
                ReplicationHelper::DELETE_JOB_CODE_UNIQUE_FIELD_ARRAY[$subject->getConfigPath()] :
                ReplicationHelper::JOB_CODE_UNIQUE_FIELD_ARRAY[$subject->getConfigPath()];
        } else {
            $uniqueAttributes = ReplicationHelper::JOB_CODE_UNIQUE_FIELD_ARRAY[$subject->getConfigPath()];
        }
        $checksum    = $subject->getHashGivenString($source->getData());
        $uniqueAttributesHash = $subject->generateIdentityValue($uniqueAttributes, $source, $properties);
        $entityArray = $this->checkEntityExistByAttributes(
            $subject,
            $uniqueAttributes,
            $source,
            $properties
        );

        if (!empty($entityArray) && $source->getIsDeleted()) {
            foreach ($entityArray as $entity) {
                $entity->setIsFailed(0);
                $entity->setUpdatedAt($subject->rep_helper->getDateTime());
                $entity->setIsDeleted(1);
                $entity->setProcessed(0);
                try {
                    if ($entity->getNavId()) {
                        $subject->getRepository()->save($entity);
                    }
                } catch (\Exception $e) {
                    $subject->logger->debug($e->getMessage());
                }
            }
        } else {
            if (!empty($entityArray)) {
                $entity = reset($entityArray);
                $entity->setIsUpdated(1);
                $entity->setIsFailed(0);
                $entity->setUpdatedAt($subject->rep_helper->getDateTime());
            } else {
                $entity = $subject->getFactory()->create();
            }
            if ($entity->getChecksum() != $checksum) {
                $entity->addData(
                    [
                        'checksum' => $checksum,
                        'identity_value' => $uniqueAttributesHash,
                        'scope' => $source->getScope(),
                        'scope_id' => $source->getScopeId()
                    ]
                );
                foreach ($properties as $propertyIndex => $property) {
                    $entity->setData($property, $source->getData($propertyIndex));
                }

                $mappings = \Ls\Replication\Helper\ReplicationHelper::DB_TABLES_MAPPING;
                foreach ($mappings as $mapping) {
                    if (\Ls\Replication\Helper\ReplicationHelper::TABLE_NAME_PREFIX . $mapping['table_name'] ==
                        $entity->getResource()->getMainTable()
                    ) {
                        $columnsMapping = $mapping['columns_mapping'];
                        foreach ($columnsMapping as $columnName => $columnMapping) {
                            if ($entity->hasData($columnName)) {
                                $entity->setData(
                                    is_array($columnMapping) ? $columnMapping['name'] : $columnMapping,
                                    $entity->getData($columnName)
                                );
                            }
                        }
                        break;
                    }
                }
            }

            try {
                $entity->setIsDeleted(0);
                $subject->getRepository()->save($entity);
            } catch (\Exception $e) {
                $subject->logger->debug($e->getMessage());
            }
        }

        return null;
    }

    /**
     * Check entity exists
     *
     * @param AbstractReplicationTask $subject
     * @param array $uniqueAttributes
     * @param mixed $source
     * @param $properties
     * @return mixed
     */
    public function checkEntityExistByAttributes(
        AbstractReplicationTask $subject,
        $uniqueAttributes,
        $source,
        $properties
    ) {
        $criteria = $subject->getSearchCriteria();

        foreach ($uniqueAttributes as $index => $attribute) {
            $key = array_search($index, $properties);

            if ($key === false) {
                $key = $index;
            }

            $sourceValue = $source->getData($key);

            if (!$source->getIsDeleted() && $sourceValue == "") {
                $criteria->addFilter($attribute, true, 'null');
            } elseif (!$source->getIsDeleted() ||
                ($source->getIsDeleted() &&
                    ($attribute == 'scope_id' || $attribute == 'Code' || $attribute == 'SubCode')
                )
            ) {
                $criteria->addFilter($attribute, $sourceValue);
            }
        }

        $result = $subject->getRepository()->getList($criteria->create());
        return $result->getItems();
    }
}
