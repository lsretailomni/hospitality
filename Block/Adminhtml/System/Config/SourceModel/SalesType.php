<?php

namespace Ls\Hospitality\Block\Adminhtml\System\Config\SourceModel;

use GuzzleHttp\Exception\GuzzleException;
use \Ls\Core\Model\LSR;
use \Ls\Omni\Helper\StoreHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;

class SalesType implements OptionSourceInterface
{
    /**
     * @param StoreHelper $storeHelper
     * @param LSR $lsr
     * @param RequestInterface $request
     */
    public function __construct(
        public StoreHelper $storeHelper,
        public LSR $lsr,
        public RequestInterface $request
    ) {
    }

    /**
     * Loading sales type information for particular store
     *
     * @return array
     * @throws NoSuchEntityException|GuzzleException
     */
    public function toOptionArray()
    {
        $salesTypeArray[] = [
            'value' => '',
            'label' => __('Please select sales type')
        ];
        // Get current Website Id.
        $websiteId = (int)$this->request->getParam('website');

        if ($this->lsr->isLSR($websiteId, ScopeInterface::SCOPE_WEBSITE)) {
            $salesType = $this->storeHelper->getSalesType($websiteId);
            if ($salesType) {
                foreach ($salesType as $item) {
                    $salesTypeArray[] = [
                        'value' => $item->getCode(),
                        'label' => __($item->getDescription())
                    ];
                }
            }
        }

        return $salesTypeArray;
    }
}
