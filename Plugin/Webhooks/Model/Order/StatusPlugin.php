<?php
declare(strict_types=1);

namespace Ls\Hospitality\Plugin\Webhooks\Model\Order;

use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Webhooks\Model\Order\Shipment;
use \Ls\Webhooks\Model\Order\Status;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class StatusPlugin
{
    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var LSR
     */
    public $lsr;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param LSR $lsr
     * @param Shipment $shipment
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        LSR $lsr,
        Shipment $shipment
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
        $this->lsr = $lsr;
    }

    /**
     * Before plugin to fake lines in case of hospitality
     *
     * @param Status $subject
     * @param array $data
     * @return array
     * @throws NoSuchEntityException
     */
    public function beforeProcess(Status $subject, $data)
    {
        if (!empty($data) && !empty($data['OrderId']) && !empty($data['HeaderStatus'] && empty($data['Lines']))) {
            $this->hospitalityHelper->fakeOrderLinesStatusWebhook($data);
        }

        return [$data];
    }

    /**
     * Before plugin to create invoice or shipment for hospitality based on orderKotStatus
     *
     * @param Status $subject
     * @param $status
     * @param $itemsInfo
     * @param $magOrder
     * @param $data
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     */
    public function beforeCheckAndProcessStatus(Status $subject, $status, $itemsInfo, $magOrder, $data)
    {
        $magentoOrder           = $this->hospitalityHelper->getOrderByDocumentId($data['OrderId']);
        $isClickAndCollectOrder = $subject->helper->isClickAndcollectOrder($magOrder);
        if (!empty($magentoOrder) && $this->lsr->isHospitalityStore($magentoOrder->getStoreId())) {
            $storeId           = $magOrder->getStoreId();
            $invoiceKotStatus  = $this->lsr->getStoreConfig(
                LSR::SC_INVOICE_KOTSTATUS,
                $storeId
            );
            $shipmentKotStatus = $this->lsr->getStoreConfig(
                LSR::SC_SHIPMENT_KOTSTATUS,
                $storeId
            );

            if (!$isClickAndCollectOrder) {
                if (isset($data['orderKOTStatus']) && $shipmentKotStatus == $data['orderKOTStatus'] && $magentoOrder->canShip()) {
                    $subject->payment->createShipment($magentoOrder, $data['Lines']);
                }
            }

            if (isset($data['orderKOTStatus']) && $invoiceKotStatus == $data['orderKOTStatus'] && $magentoOrder->canInvoice()) {
                $subject->payment->generateInvoice($data);
            }
        }

        return [$status, $itemsInfo, $magOrder, $data];
    }
}
