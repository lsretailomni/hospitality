<?php

namespace Ls\Hospitality\Plugin\Ui\DataProvider;

use Exception;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Ui\DataProvider\CronsProvider;
use Magento\Store\Model\ScopeInterface;

/**
 * Plugin for CronsProviderPlugin
 */
class CronsProviderPlugin
{

    /**
     * @param CronsProvider $subject
     * @param $result
     * @return array
     */
    public function afterReadCronFile(CronsProvider $subject, $result)
    {
        try {
            $filePath        = $subject->moduleDirReader->getModuleDir('etc', 'Ls_Hospitality') . '/crontab.xml';
            $parsedArray     = $subject->parser->load($filePath)->xmlToArray();
            $hospitalityJobs = $parsedArray['config']['_value']['group'];
            // merge both data.
            return array_merge($hospitalityJobs, $result);
        } catch (Exception $e) {
            // just return base data.
            return $result;
        }
    }

    /**
     * @param CronsProvider $subject
     * @param $result
     * @param null $cronName
     * @param null $storeId
     * @return string
     */
    public function afterGetStatusByCronCode(
        CronsProvider $subject,
        $result,
        $cronName = null,
        $storeId = null
    ) {
        if ($cronName == 'process_item_modifier') {
            $fullReplicationStatus = $subject->lsr->getConfigValueFromDb(
                LSR::SC_SUCCESS_CRON_ITEM_MODIFIER,
                ScopeInterface::SCOPE_STORES,
                $storeId
            );
            $result                = $fullReplicationStatus;
        }

        if ($cronName == 'process_item_recipe') {
            $fullReplicationStatus = $subject->lsr->getConfigValueFromDb(
                LSR::SC_SUCCESS_CRON_ITEM_RECIPE,
                ScopeInterface::SCOPE_STORES,
                $storeId
            );
            $result                = $fullReplicationStatus;
        }

        if ($cronName == 'process_item_deal') {
            $fullReplicationStatus = $subject->lsr->getConfigValueFromDb(
                LSR::SC_SUCCESS_CRON_ITEM_DEAL,
                ScopeInterface::SCOPE_STORES,
                $storeId
            );
            $result                = $fullReplicationStatus;
        }
        return $result;
    }
}
