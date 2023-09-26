<?php

namespace Ls\Hospitality\Plugin\Controller\Adminhtml\Deletion;

use \Ls\Hospitality\Model\LSR;
use Ls\Replication\Controller\Adminhtml\Deletion\LsTables;

/**
 * LsTables plugin for truncating specific deal html data
 */
class LsTablesPlugin
{
    /**
     * Reset specific cron data
     *
     * @param LsTables $subject
     * @param $jobName
     * @param $scopeId
     * @param $coreConfigTableName
     * @return array
     */
    public function beforeResetSpecificCronData(LsTables $subject, $jobName, $scopeId, $coreConfigTableName)
    {
        if ($jobName == LSR::SC_ITEM_DEAL_HTML_JOB_CODE) {
            $replicationTableName = 'ls_replication_repl_data_translation';
            $subject->replicationHelper->deleteGivenTableDataGivenConditions(
                $replicationTableName,
                [
                    'TranslationId = ?' => LSR::SC_TRANSLATION_ID_DEAL_ITEM_HTML,
                    'scope_id = ?'      => $scopeId
                ]
            );
        }

        foreach (LsTables::TABLE_CONFIGS as $config) {
            $subject->replicationHelper->deleteGivenTableDataGivenConditions(
                $coreConfigTableName,
                [
                    'path = ?' => $config . $jobName,
                    'scope_id = ?' => $scopeId
                ]
            );
        }

        return [$jobName, $scopeId, $coreConfigTableName];
    }
}
