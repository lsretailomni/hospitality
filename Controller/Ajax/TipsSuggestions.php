<?php
declare(strict_types=1);

namespace Ls\Hospitality\Controller\Ajax;

use Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class TipsSuggestions implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var HospitalityHelper
     */
    private HospitalityHelper $hospitalityHelper;

    public function __construct(
        JsonFactory $resultJsonFactory,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->hospitalityHelper = $hospitalityHelper;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $suggestions = $this->hospitalityHelper->getTipsSuggestionsFromStore();

            return $result->setData([
                'success' => true,
                'suggestions' => is_array($suggestions) ? $suggestions : []
            ]);
        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
                'suggestions' => []
            ]);
        }
    }
}