<?php

namespace Ls\Hospitality\Plugin\Cron;

use \Ls\Replication\Cron\AbstractReplicationTask;
use Magento\Framework\App\ObjectManager;

/**
 * Interceptor to intercept AbstractReplicationTask
 */
class AbstractReplicationTaskPlugin
{
    /**
     * After plugin to set the respective app_id and full_replication
     *
     * @param AbstractReplicationTask $subject
     * @param $result
     * @param $properties
     * @param $source
     * @return mixed
     */
    public function afterSaveSource(AbstractReplicationTask $subject, $result, $properties, $source)
    {
        if ($subject->getConfigPath()!="ls_mag/replication/repl_item_modifier" &&
            $subject->getConfigPath() != "ls_mag/replication/repl_item_recipe"
        ) {
            return $result;
        }

        if ($source->getIsDeleted()) {
            $uniqueAttributes = (array_key_exists($subject->getConfigPath(), AbstractReplicationTask::$deleteJobCodeUniqueFieldArray)) ?
                AbstractReplicationTask::$deleteJobCodeUniqueFieldArray[$subject->getConfigPath()] :
                AbstractReplicationTask::$jobCodeUniqueFieldArray[$subject->getConfigPath()];
        } else {
            $uniqueAttributes = AbstractReplicationTask::$jobCodeUniqueFieldArray[$subject->getConfigPath()];
        }
        // phpcs:ignore Magento2.Security.InsecureFunction
        $checksum    = crc32(serialize($source));
        $entityArray = $this->checkEntityExistByAttributes($subject->getRepository(), $uniqueAttributes, $source);

        if (!empty($entityArray) && $source->getIsDeleted()) {
            foreach ($entityArray as $entity) {
                $entity->setIsFailed(0);
                $entity->setUpdatedAt($subject->rep_helper->getDateTime());
                $entity->setIsDeleted(1);
                $entity->setProcessed(0);
                try {
                    if($entity->getNavId()) {
                        $subject->getRepository()->save($entity);
                    }
                } catch (\Exception $e) {
                    $subject->logger->debug($e->getMessage());
                }
            }
        } else {
            if (!empty($entityArray)) {
                foreach ($entityArray as $value) {
                    $entity = $value;
                }
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
                        $set_method = 'setNavId';
                        $get_method = 'getId';
                    } else {
                        $field_name_optimized   = str_replace('_', ' ', $property);
                        $field_name_capitalized = ucwords($field_name_optimized);
                        $field_name_capitalized = str_replace(' ', '', $field_name_capitalized);
                        $set_method             = "set$field_name_capitalized";
                        $get_method             = "get$field_name_capitalized";
                    }
                    if ($entity && $source && method_exists($entity, $set_method) && method_exists($source, $get_method)) {
                        $entity->{$set_method}($source->{$get_method}());
                    }
                }
                try {
                    if($source->getId()) {
                        $entity->setIsDeleted(0);
                        $subject->getRepository()->save($entity);
                    }
                } catch (\Exception $e) {
                    $subject->logger->debug($e->getMessage());
                }
            }
        }

        return $result;
    }

    /**
    * @param $subjectRepository
    * @param $uniqueAttributes
    * @param $source
    * @param $notAnArraysObject
    * @return mixed
     */
    public function checkEntityExistByAttributes($subjectRepository, $uniqueAttributes, $source, $notAnArraysObject = false)
    {
        $objectManager = $this->getObjectManager();
        // @codingStandardsIgnoreStart
        $criteria = $objectManager->get('Magento\Framework\Api\SearchCriteriaBuilder');
        // @codingStandardsIgnoreEnd
        foreach ($uniqueAttributes as $attribute) {
            $field_name_optimized   = str_replace('_', ' ', $attribute);
            $field_name_capitalized = ucwords($field_name_optimized);
            $field_name_capitalized = str_replace(' ', '', $field_name_capitalized);

            if ($attribute == 'nav_id') {
                $get_method = 'getId';
            } else {
                $get_method = "get$field_name_capitalized";
            }

            if ($notAnArraysObject) {
                foreach ($source as $keyprop => $valueprop) {
                    if ($get_method == 'get' . $keyprop) {
                        $sourceValue = $valueprop;
                        if ($sourceValue != '') {
                            break;
                        }
                    }
                }
            } else {
                $sourceValue = $source->{$get_method}();
            }

            if (!$source->getIsDeleted() && $sourceValue == "") {
                $criteria->addFilter($attribute, true, 'null');
            } elseif (!$source->getIsDeleted() || ($source->getIsDeleted() && ($attribute == 'scope_id' || $attribute == 'Code' || $attribute == 'SubCode'))) {
                $criteria->addFilter($attribute, $sourceValue);
            }
        }
        $result = $subjectRepository->getList($criteria->create());
        return $result->getItems();
    }

    /**
     * Better to use this function when we need Object Manger in order to Organize all code in single place.
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return ObjectManager::getInstance();
    }
}
