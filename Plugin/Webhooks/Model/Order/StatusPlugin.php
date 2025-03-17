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
        $this->lsr               = $lsr;
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
        } elseif (!empty($data['orderKOTStatus'])) {
            $this->hospitalityHelper->fixOrderLinesStatus($data);
        }
        return [$data];
    }

    /**
     * Before plugin to create invoice or shipment for hospitality based on orderKotStatus
     *
     * @param Status $subject
     * @param $status
     * @param $itemsInfo
     * @param $magentoOrder
     * @param $data
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     */
    public function beforeCheckAndProcessStatus(Status $subject, $status, $itemsInfo, $magentoOrder, $data)
    {
        $mgOrder       = $this->hospitalityHelper->getOrderByDocumentId($data['OrderId'], true);
        $magentoOrders = is_array($mgOrder) ? $mgOrder : [$mgOrder];
        $dataInfo = $data;
        foreach ($magentoOrders as $magOrder) {
            if (!empty($magOrder) && $this->lsr->isHospitalityStore($magOrder->getStoreId())) {
                if (count($magentoOrders) > 1) {
                    $lines         = $this->hospitalityHelper->fixOrderLinesStatusWebhookGroupOrdering($dataInfo,
                        $magOrder);
                    $data['Lines'] = $lines;
                }
                $isClickAndCollectOrder = $subject->helper->isClickAndcollectOrder($magOrder);
                $storeId                = $magOrder->getStoreId();
                $invoiceKotStatus       = $this->lsr->getStoreConfig(
                    LSR::SC_INVOICE_KOTSTATUS,
                    $storeId
                );
                $invoiceKotStatus       = 'Served';
                $shipmentKotStatus      = $this->lsr->getStoreConfig(
                    LSR::SC_SHIPMENT_KOTSTATUS,
                    $storeId
                );
                $shipmentKotStatus      = 'Served';

                if (!$isClickAndCollectOrder) {
                    if (isset($data['orderKOTStatus']) && $shipmentKotStatus == $data['orderKOTStatus']
                        && $magOrder->canShip()) {
                        $subject->payment->createShipment($magOrder, $data['Lines']);
                    }
                }

                if (isset($data['orderKOTStatus']) && $invoiceKotStatus == $data['orderKOTStatus']
                    && $magOrder->canInvoice()) {
                    $subject->payment->generateInvoice($data, true, $magOrder);
                }
            }
        }

        return [$status, $itemsInfo, $magentoOrders, $data];
    }
}
