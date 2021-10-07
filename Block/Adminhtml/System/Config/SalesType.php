<?php

namespace Ls\Hospitality\Block\Adminhtml\System\Config;

use \Ls\Core\Model\LSR;
use \Ls\Hospitality\Helper\StoreHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * For getting salestype
 */
class SalesType implements OptionSourceInterface
{
    /** @var StoreHelper */
    public $storeHelper;

    /** @var LSR */
    public $lsr;

    /** @var RequestInterface */
    public $request;

    /**
     * @param StoreHelper $storeHelper
     * @param LSR $lsr
     * @param RequestInterface $request
     */
    public function __construct(
        StoreHelper $storeHelper,
        LSR $lsr,
        RequestInterface $request
    ) {
        $this->storeHelper = $storeHelper;
        $this->lsr         = $lsr;
        $this->request     = $request;
    }

    /**
     * Loading sales type information for particular store
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function toOptionArray()
    {
        $salesTypeArray[] = [
            'value' => '',
            'label' => __('Please select sales type')
        ];
        // Get current Website Id.
        $websiteId = (int)$this->request->getParam('website');
        if ($this->lsr->isLSR($websiteId, 'website')) {
            $salesType = $this->storeHelper->getSalesType($websiteId);
            if ($salesType) {
                $data = $salesType->getHospSalesTypes();
                foreach ($data as $item) {
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
