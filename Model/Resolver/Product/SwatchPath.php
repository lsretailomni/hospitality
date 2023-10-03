<?php
declare(strict_types=1);

namespace Ls\Hospitality\Model\Resolver\Product;

use \Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Images;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Image\Placeholder as PlaceholderProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\UrlInterface;
use Magento\Catalog\Model\Product\ImageFactory;

class SwatchPath implements ResolverInterface
{
    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var PlaceholderProvider
     */
    public $placeholderProvider;

    /**
     * @var ImageFactory
     */
    public $productImageFactory;

    /**
     * @param HospitalityHelper $hospitalityHelper
     * @param PlaceholderProvider $placeholderProvider
     * @param ImageFactory $productImageFactory
     */
    public function __construct(
        HospitalityHelper $hospitalityHelper,
        PlaceholderProvider $placeholderProvider,
        ImageFactory $productImageFactory
    ) {
        $this->hospitalityHelper   = $hospitalityHelper;
        $this->placeholderProvider = $placeholderProvider;
        $this->productImageFactory = $productImageFactory;
    }

    /**
     * Add proper swatch image path
     *
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     *
     * @return string
     *
     * @throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($value['swatch']) || empty($value['swatch'])) {
            $image = $this->productImageFactory->create();
            $image->setDestinationSubdir(Images::CODE_SWATCH_IMAGE)
                ->setBaseFile('no_selection');

            if ($image->isBaseFilePlaceholder()) {
                return $this->placeholderProvider->getPlaceholder(Images::CODE_SWATCH_IMAGE);
            }
        }

        return $this->hospitalityHelper
                ->storeManager
                ->getStore()
                ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . $value['swatch'];
    }
}
