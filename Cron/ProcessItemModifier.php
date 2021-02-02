<?php

namespace Ls\Hospitality\Cron;

use Exception;
use \Ls\Hospitality\Helper\HospitalityHelper;
use \Ls\Hospitality\Model\LSR;
use \Ls\Replication\Api\ReplItemModifierRepositoryInterface;
use \Ls\Replication\Helper\ReplicationHelper;
use \Ls\Replication\Logger\Logger;
use \Ls\Replication\Model\ReplItemModifier;
use \Ls\Replication\Model\ResourceModel\ReplItemModifier\Collection;
use \Ls\Replication\Model\ResourceModel\ReplItemModifier\CollectionFactory as ReplItemModifierCollectionFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterface;
use Magento\Catalog\Api\Data\ProductCustomOptionValuesInterfaceFactory;
use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Class ProcessItemModifier
 * To Process Item Modifiers into the Magento data structure.
 */
class ProcessItemModifier
{
    /** @var bool */
    public $cronStatus = false;

    /** @var int */
    public $remainingRecords;

    /** @var LSR */
    public $lsr;

    /** @var ReplicationHelper */
    public $replicationHelper;

    /** @var Logger */
    public $logger;

    /** @var StoreInterface $store */
    public $store;

    /** @var ReplItemModifierCollectionFactory */
    public $replItemModifierCollectionFactory;

    /** @var ReplItemModifierRepositoryInterface */
    public $replItemModifierRepositoryInterface;

    /** @var ProductRepositoryInterface */
    public $productRepository;

    /** @var ProductCustomOptionInterfaceFactory */
    public $customOptionFactory;

    /** @var ProductCustomOptionRepositoryInterface */
    public $optionRepository;

    /** @var ProductCustomOptionValuesInterfaceFactory */
    public $customOptionValueFactory;

    /**
     * @var HospitalityHelper
     */
    public $hospitalityHelper;

    public static $triggerFunctionToSkip = ['Infocode'];

    /**
     * ProcessItemModifier constructor.
     * @param ReplicationHelper $replicationHelper
     * @param Logger $logger
     * @param LSR $LSR
     * @param ReplItemModifierCollectionFactory $replItemModifierCollectionFactory
     * @param ReplItemModifierRepositoryInterface $replItemModifierRepositoryInterface
     * @param ProductRepositoryInterface $productRepository
     * @param ProductCustomOptionRepositoryInterface $optionRepository
     * @param ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory
     * @param ProductCustomOptionInterfaceFactory $customOptionFactory
     * @param HospitalityHelper $hospitalityHelper
     */
    public function __construct(
        ReplicationHelper $replicationHelper,
        Logger $logger,
        LSR $LSR,
        ReplItemModifierCollectionFactory $replItemModifierCollectionFactory,
        ReplItemModifierRepositoryInterface $replItemModifierRepositoryInterface,
        ProductRepositoryInterface $productRepository,
        ProductCustomOptionRepositoryInterface $optionRepository,
        ProductCustomOptionValuesInterfaceFactory $customOptionValueFactory,
        ProductCustomOptionInterfaceFactory $customOptionFactory,
        HospitalityHelper $hospitalityHelper
    ) {
        $this->logger                              = $logger;
        $this->replicationHelper                   = $replicationHelper;
        $this->lsr                                 = $LSR;
        $this->replItemModifierCollectionFactory   = $replItemModifierCollectionFactory;
        $this->replItemModifierRepositoryInterface = $replItemModifierRepositoryInterface;
        $this->productRepository                   = $productRepository;
        $this->customOptionFactory                 = $customOptionFactory;
        $this->customOptionValueFactory            = $customOptionValueFactory;
        $this->optionRepository                    = $optionRepository;
        $this->hospitalityHelper                   = $hospitalityHelper;
    }

    /**
     * @param null $storeData
     * @throws NoSuchEntityException
     */
    public function execute($storeData = null)
    {
        if (!empty($storeData) && $storeData instanceof StoreInterface) {
            $stores = [$storeData];
        } else {
            /** @var StoreInterface[] $stores */
            $stores = $this->lsr->getAllStores();
        }
        if (!empty($stores)) {
            foreach ($stores as $store) {
                $this->lsr->setStoreId($store->getId());
                $this->store = $store;
                if ($this->lsr->isLSR($this->store->getId())
                    && $this->lsr->isHospitalityStore($store->getId())
                ) {
                    $this->replicationHelper->updateConfigValue(
                        $this->replicationHelper->getDateTime(),
                        LSR::SC_ITEM_MODIFIER_CONFIG_PATH_LAST_EXECUTE,
                        $this->store->getId()
                    );
                    $this->logger->debug('Running ProcessItemModifier Task for store ' . $this->store->getName());
                    $this->processItemModifiers();
                    $this->replicationHelper->updateCronStatus(
                        $this->cronStatus,
                        LSR::SC_SUCCESS_CRON_ITEM_MODIFIER,
                        $this->store->getId()
                    );
                    $this->logger->debug(
                        'End ProcessItemModifier Task with remaining : ' . $this->getRemainingRecords($this->store)
                    );
                }
                $this->lsr->setStoreId(null);
            }
        }
    }

    /**
     * @param null $storeData
     * @return array
     * @throws NoSuchEntityException
     */
    public function executeManually($storeData = null)
    {
        $this->execute($storeData);
        $remainingRecords = (int)$this->getRemainingRecords($storeData, true);
        return [$remainingRecords];
    }

    /**
     * Item modifier processing
     */
    public function processItemModifiers()
    {
        //TODO cover the delete scenario.
        $batchSize = $this->hospitalityHelper->getItemModifiersBatchSize();
        $filters   = [
            ['field' => 'main_table.scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq']
        ];

        $criteria = $this->replicationHelper->buildCriteriaForArrayWithAlias(
            $filters,
            $batchSize,
            1
        );
        /** @var Collection $collection */
        $collection = $this->replItemModifierCollectionFactory->create();
        $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
            $collection,
            $criteria,
            'nav_id',
            null,
            'catalog_product_entity',
            'sku'
        );
        $dataToProcess = [];

        if ($collection->getSize() > 0) {
            /** @var ReplItemModifier $itemModifier */
            foreach ($collection->getItems() as $itemModifier) {
                /**
                 * There are types of Modifiers which we dont need to process
                 * i-e All modifiers whose TriggerFunction = Infocode
                 **/
                if (!in_array($itemModifier->getTriggerFunction(), self::$triggerFunctionToSkip)) {
                    $dataToProcess[$itemModifier->getNavId()][$itemModifier->getCode()][$itemModifier->getSubCode()] = $itemModifier;
                } else {
                    // close these values as we will not process those because of InfoCodes
                    $itemModifier->setProcessed(1)
                        ->setProcessedAt($this->replicationHelper->getDateTime())
                        ->setIsUpdated(0);

                    $this->replItemModifierRepositoryInterface->save($itemModifier);
                }
            }

            if (!empty($dataToProcess)) {
                // loop against each Product.
                foreach ($dataToProcess as $itemSKU => $optionArray) {
                    // generate options.
                    $productOptions = [];
                    if (!empty($optionArray)) {
                        // get Product Repository;
                        /** @var  $product */
                        try {
                            $product         = $this->productRepository->get(
                                $itemSKU,
                                true,
                                $this->store->getId()
                            );
                            $existingOptions = $this->optionRepository->getProductOptions($product);
                            if (!$product->getHasOptions()) {
                                $product->setHasOptions(1);
                                $product = $this->productRepository->save($product);
                            }
                            foreach ($optionArray as $optionCode => $optionValuesArray) {
                                $isOptionExist         = false;
                                $ls_modifier_recipe_id = LSR::LSR_ITEM_MODIFIER_PREFIX . $optionCode;
                                if (!empty($existingOptions)) {
                                    foreach ($existingOptions as $existingOption) {
                                        if ($existingOption->getData('ls_modifier_recipe_id') == $ls_modifier_recipe_id) {
                                            $isOptionExist = true;
                                            $productOption = $existingOption;
                                            break;
                                        }
                                    }
                                }
                                if (!$isOptionExist) {
                                    /** @var ProductCustomOptionInterface $productOption */
                                    $productOption = $this->customOptionFactory->create();
                                }
                                $optionNeedsToBeUpdated = false;
                                if (!empty($optionValuesArray)) {
                                    $optionData = [];
                                    /** @var ReplItemModifier $optionValueData */
                                    foreach ($optionValuesArray as $subcode => $optionValueData) {
                                        $existingOptionValues = $productOption->getValues();
                                        /** @var ProductCustomOptionValuesInterface $optionValue */
                                        if ($optionValueData->getExplanatoryHeaderText() != '') {
                                            $title = $optionValueData->getExplanatoryHeaderText();
                                        } else {
                                            $title = $optionValueData->getCode();
                                        }
                                        /**
                                         * Dev Notes:
                                         * For the Option Values, we are only checking the duplication based on title.
                                         */
                                        $isOptionValueExist = false;
                                        if (!empty($existingOptionValues)) {
                                            foreach ($existingOptionValues as $existingOptionValue) {
                                                if ($existingOptionValue->getTitle() == $optionValueData->getDescription()) {
                                                    $isOptionValueExist = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (!$isOptionValueExist) {
                                            $optionNeedsToBeUpdated = true;
                                            $optionValue            = $this->customOptionValueFactory->create();
                                            $optionValue->setTitle($optionValueData->getDescription())
                                                ->setPriceType('fixed')
                                                ->setSortOrder($subcode)
                                                ->setPrice($optionValueData->getAmountPercent());
                                            $optionData['values'][] = $optionValue;
                                            $optionData['title']    = $title;
                                        }

                                        $optionValueData->setProcessed(1)
                                            ->setProcessedAt($this->replicationHelper->getDateTime())
                                            ->setIsUpdated(0);

                                        $this->replItemModifierRepositoryInterface->save($optionValueData);
                                        //$this->logger->debug(var_export($optionValueData, true));
                                    }
                                    /**
                                     * TODO set type dynamic based on minimum and maximum value
                                     * set require based on minimum and maximum value
                                     * set title based from first option text.
                                     */
                                    if ($optionNeedsToBeUpdated) {
                                        try {
                                            // check if Option
                                            $productOption->setTitle($optionData['title'])
                                                ->setPrice('')
                                                ->setPriceType('fixed')
                                                ->setValues($optionData['values'])
                                                ->setIsRequire(0)
                                                ->setType('drop_down')
                                                ->setData('ls_modifier_recipe_id', $ls_modifier_recipe_id)
                                                ->setProductSku($itemSKU);
                                            $savedProductOption = $this->optionRepository->save($productOption);
                                            $product->addOption($savedProductOption);
                                        } catch (Exception $e) {
                                            $this->logger->error($e->getMessage());
                                            $this->logger->error(
                                                'Error while creating options for' . $optionCode . ' for product ' . $itemSKU . ' for store ' . $this->store->getName()
                                            );
                                        }
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            $this->logger->error($e->getMessage());
                            $this->logger->error(
                                'Error while creating modifiers for  ' . $itemSKU . ' for store ' . $this->store->getName()
                            );
                        }
                    }
                }
            }
            $remainingItems = (int)$this->getRemainingRecords($this->store);
            if ($remainingItems == 0) {
                $this->cronStatus = true;
            }
        } else {
            $this->cronStatus = true;
        }
    }

    /**
     * @param $storeData
     * @param false $forceReload
     * @return int
     */
    public function getRemainingRecords(
        $storeData,
        $forceReload = false
    ) {
        if (!$this->remainingRecords || $forceReload) {
            $filters = [
                ['field' => 'main_table.scope_id', 'value' => $this->store->getId(), 'condition_type' => 'eq']
            ];

            $criteria   = $this->replicationHelper->buildCriteriaForArrayWithAlias(
                $filters,
                -1,
                1
            );
            $collection = $this->replItemModifierCollectionFactory->create();
            $this->replicationHelper->setCollectionPropertiesPlusJoinSku(
                $collection,
                $criteria,
                'nav_id',
                null,
                'catalog_product_entity',
                'sku'
            );
            $this->remainingRecords = $collection->getSize();
        }
        return $this->remainingRecords;
    }
}
