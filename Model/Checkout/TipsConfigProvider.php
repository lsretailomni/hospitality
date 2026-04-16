<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Checkout;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Checkout\Model\ConfigProviderInterface;

class TipsConfigProvider implements ConfigProviderInterface
{
    /**
     * @var HospitalityHelper
     */
    private HospitalityHelper $hospitalityHelper;

    public function __construct(
        HospitalityHelper $hospitalityHelper
    ) {
        $this->hospitalityHelper = $hospitalityHelper;
    }

    public function getConfig(): array
    {
        $tipsEnabled = (bool)$this->hospitalityHelper->isTipsEnabled();

        $suggestions = [];
        if ($tipsEnabled) {
            $suggestions = $this->hospitalityHelper->getTipsSuggestionsFromStore();

            if (!is_array($suggestions)) {
                $suggestions = [];
            }
        }

        return [
            'lsHospitality' => [
                'tips' => [
                    'enabled' => $tipsEnabled,
                    'suggestions' => $suggestions
                ]
            ]
        ];
    }
}