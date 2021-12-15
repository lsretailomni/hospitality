<?php

namespace Ls\Hospitality\Plugin\Cron;

use \Ls\Replication\Cron\SyncImages;
use \Ls\Replication\Model\ResourceModel\ReplImageLink\Collection;

/**
 * Plugin to merge two collections
 */
class SyncImagesPlugin
{
    /**
     * @param SyncImages $subject
     * @param $result
     * @param false $totalCount
     * @return Collection
     */
    public function afterGetRecordsForImagesToProcess(SyncImages $subject, $result, $totalCount = false)
    {
        if (!$totalCount) {
            $batchSize = $subject->replicationHelper->getProductImagesBatchSize() - $result->getSize();
        } else {
            $batchSize = -1;
        }
        if ($batchSize !=0) {
            /** Get Images for only those items which are already processed */
            $filters  = [
                ['field' => 'main_table.TableName', 'value' => '%Offer', 'condition_type' => 'like'],
                ['field' => 'main_table.scope_id', 'value' => $subject->store->getId(), 'condition_type' => 'eq']
            ];
            $criteria = $subject->replicationHelper->buildCriteriaForArrayWithAlias(
                $filters,
                $batchSize,
                false
            );
            /** @var  $collection */
            $collection = $subject->replImageLinkCollectionFactory->create();

            /** we only need unique product Id's which has any images to modify */
            $subject->replicationHelper->setCollectionPropertiesPlusJoinsForImages(
                $collection,
                $criteria,
                'Offer'
            );
            $collection->getSelect()->order('main_table.processed ASC');
            $mergedCollectionIds = array_merge($result->getAllIds(), $collection->getAllIds());
            $collection          = $subject->replImageLinkCollectionFactory->create()
                ->addFieldToFilter('repl_image_link_id', ['in' => $mergedCollectionIds])
                ->addFieldToSelect('*')
                ->setPageSize($batchSize);
            return $collection;
        }
        return $result;
    }
}
