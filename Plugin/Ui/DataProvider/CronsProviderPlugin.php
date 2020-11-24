<?php

namespace Ls\Hospitality\Plugin\Ui\DataProvider;


/**
 * Class CronsProviderPlugin
 */
class CronsProviderPlugin
{

    /**
     * @return mixed
     */
    public function afterReadCronFile(\Ls\Replication\Ui\DataProvider\CronsProvider $subject, $result)
    {
        try {
            $filePath          = $subject->moduleDirReader->getModuleDir('etc', 'Ls_Hospitality') . '/crontab.xml';
            $parsedArray       = $subject->parser->load($filePath)->xmlToArray();
            $hospitalityJobs[] = $parsedArray['config']['_value']['group'];
            // merge both data.
            return array_merge($hospitalityJobs, $result);
        } catch (\Exception $e) {
            // just return base data.
            return $result;
        }
    }
}
