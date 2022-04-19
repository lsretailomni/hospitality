<?php

namespace Ls\Hospitality\Plugin\Controller\Adminhtml\Product;

use Ls\Hospitality\Helper\HospitalityHelper;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Save;
use Magento\Framework\Exception\NoSuchEntityException;

class SavePlugin
{
    /**
     * @var ProductRepositoryInterface
     */
    public $productRepository;

    /** @var ProductCustomOptionRepositoryInterface */
    public $optionRepository;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    public $logger;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductCustomOptionRepositoryInterface $optionRepository
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductCustomOptionRepositoryInterface $optionRepository,
        HospitalityHelper $hospitalityHelper,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->optionRepository = $optionRepository;
        $this->hospitalityHelper = $hospitalityHelper;
        $this->logger = $logger;
    }

    /**
     * @param Save $subject
     * @param callable $proceed
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function aroundExecute(Save $subject, callable $proceed)
    {
        $productId = $subject->getRequest()->getParam('id');
        $result = $proceed();
        try {
            $this->handleCustomOptions($subject, $productId);
        } catch (\Exception $e) {
            $this->logger->debug(sprintf('Not not add swatch images for item %s', $productId));
        }

        return $result;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function handleCustomOptions($subject, $productId)
    {
        $product = $this->productRepository->getById($productId);
        $options = $this->optionRepository->getProductOptions($product);

        if (!empty($subject->getRequest()->getPostValue('product')['options'])) {
            $post = $subject->getRequest()->getPostValue('product')['options'];
        }

        $files = $subject->getRequest()->getFiles('product')['options'];

        foreach ($options as $optionId => &$option) {
            $fileName = $this->getFileUploadedIfFound($optionId, $post, $files);

            if ($fileName) {
                $option->setSwatch($fileName);
            }


            $values = $option->getValues();

            foreach ($values as $valueId => &$value) {
                $fileName = $this->getFileUploadedIfFound($optionId, $post, $files, $valueId);

                if ($fileName) {
                    $value->setSwatch($fileName);
                }

            }
            $option->setProductSku($product->getSku());
            $this->optionRepository->save($option);
        }
    }

    /**
     * Get File uploaded if found
     *
     * @param $optionId
     * @param $post
     * @param $files
     * @param $valueId
     * @return string|null
     */
    public function getFileUploadedIfFound($optionId, $post, $files, $valueId = null)
    {
        $filename = null;
        foreach ($post ?? [] as $i => $option) {
            if (isset($option['option_id']) && $option['option_id'] == $optionId) {
                if ($valueId) {
                    foreach ($option['values'] as $optionValueId => $optionValue) {
                        if (isset($optionValue['option_type_id']) && $optionValue['option_type_id'] == $valueId) {
                            if (isset($files[$i]) && isset($files[$i]['values']) &&
                                isset($files[$i]['values'][$optionValueId]) &&
                                isset($files[$i]['values'][$optionValueId]['swatch']) &&
                                $files[$i]['values'][$optionValueId]['swatch']['error'] === 0
                            ) {
                                $filename = $this->hospitalityHelper->uploadFile(
                                    $files[$i]['values'][$optionValueId]['swatch']
                                );
                                break;
                            }
                        }
                    }
                } else {
                    if (isset($files[$i]) && isset($files[$i]['swatch']) && $files[$i]['swatch']['error'] === 0) {
                        $filename = $this->hospitalityHelper->uploadFile($files[$i]['swatch']);
                        break;
                    }
                }
            }
        }

        return $filename;
    }
}
