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
     * After plugin to set the respective app_id and full_replication
     *
     * @param AbstractReplicationTask $subject
     * @param mixed $result
     * @param array $properties
     * @param mixed $source
     * @return mixed
     */
    public function afterSaveSource(AbstractReplicationTask $subject, $result, $properties, $source)
    {
        if ($subject->getConfigPath() != "ls_mag/replication/repl_item_modifier" &&
            $subject->getConfigPath() != "ls_mag/replication/repl_item_recipe"
        ) {
            return $result;
        }

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
        $checksum    = $subject->getHashGivenString($source);
        $entityArray = $this->checkEntityExistByAttributes($subject, $uniqueAttributes, $source);

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
                $entity->setChecksum($checksum);

                foreach ($properties as $property) {
                    if ($property === 'nav_id') {
                        $setMethod = 'setNavId';
                        $getMethod = 'getId';
                    } else {
                        $fieldNameCapitalized = str_replace(' ', '', ucwords(str_replace('_', ' ', $property)));
                        $setMethod             = "set$fieldNameCapitalized";
                        $getMethod             = "get$fieldNameCapitalized";
                    }
                    if ($entity &&
                        $source &&
                        method_exists($entity, $setMethod) &&
                        method_exists($source, $getMethod)
                    ) {
                        $entity->{$setMethod}($source->{$getMethod}());
                    }
                }
            }

            try {
                if ($source->getId()) {
                    $entity->setIsDeleted(0);
                    $subject->getRepository()->save($entity);
                }
            } catch (\Exception $e) {
                $subject->logger->debug($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Check entity exists
     *
     * @param AbstractReplicationTask $subject
     * @param array $uniqueAttributes
     * @param mixed $source
     * @return mixed
     */
    public function checkEntityExistByAttributes(AbstractReplicationTask $subject, $uniqueAttributes, $source)
    {
        $criteria = $subject->getSearchCriteria();

        foreach ($uniqueAttributes as $attribute) {
            $fieldNameCapitalized = str_replace(' ', '', ucwords(str_replace('_', ' ', $attribute)));

            if ($attribute == 'nav_id') {
                $getMethod = 'getId';
            } else {
                $getMethod = "get$fieldNameCapitalized";
            }

            $sourceValue = $source->{$getMethod}();

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
