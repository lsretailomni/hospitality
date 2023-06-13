<?php

namespace Ls\Hospitality\Plugin\Ui\DataProvider;

use Exception;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Ui\DataProvider\CronsProvider;
use Magento\Store\Model\ScopeInterface;

/**
 * Plugin for CronsProvider
 */
class CronsProviderPlugin
{

    public $translationList = [
        'repl_deal_html_translation'
    ];


    /**
     * After plugin to intercept readCronFile
     *
     * @param CronsProvider $subject
     * @param mixed $result
     * @return array|mixed
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
            $subject->logger->debug($e);
            // just return base data.
            return $result;
        }
    }

    /**
     * Before plugin to intercept translation cron job
     *
     * @param CronsProvider $subject
     * @param $cronName
     * @return array|mixed
     */
    public function beforeShowTranslationRelatedCronJobsAtStoreLevel(CronsProvider $subject, $cronName)
    {
        $subject->setTranslationList(array_merge($subject->getTranslationList(), $this->translationList));

        return [$cronName];
    }

    /**
     * After plugin to intercept getStatusByCronCode
     *
     * @param CronsProvider $subject
     * @param mixed $result
     * @param string $cronName
     * @param string $storeId
     * @return string
     */
    public function afterGetStatusByCronCode(
        CronsProvider $subject,
        $result,
        $cronName,
        $storeId
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

        if ($cronName == 'process_translation') {
            $fullReplicationStatus = $subject->lsr->getConfigValueFromDb(
                LSR::SC_SUCCESS_PROCESS_TRANSLATION,
                ScopeInterface::SCOPE_STORES,
                $storeId
            );
            $result                = $fullReplicationStatus;
        }

        return $result;
    }
}
