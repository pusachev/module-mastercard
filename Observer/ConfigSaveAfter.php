<?php
/*
 * Copyright (c) On Tap Networks Limited.
 */

namespace OnTap\MasterCard\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Api\GroupRepositoryInterface;
use Magento\Framework\Message\ManagerInterface;
use OnTap\MasterCard\Gateway\Config\ConfigFactory;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use OnTap\MasterCard\Model\SelectedStore;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigSaveAfter implements ObserverInterface
{
    /**
     * @var WebsiteRepositoryInterface
     */
    protected $websiteRepository;

    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * @var CommandPoolInterface
     */
    protected $commandPool;

    /**
     * @var SelectedStore
     */
    protected $selectedStore;

    /**
     * @var string[]
     */
    protected $methods;

    /**
     * @var ScopeConfigInterface
     */
    protected $config;

    /**
     * ConfigSaveAfter constructor.
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param GroupRepositoryInterface $groupRepository
     * @param ManagerInterface $messageManager
     * @param ConfigFactory $configFactory
     * @param CommandPoolInterface $commandPool
     * @param SelectedStore $selectedStore
     * @param ScopeConfigInterface $config
     * @param array $methods
     */
    public function __construct(
        WebsiteRepositoryInterface $websiteRepository,
        GroupRepositoryInterface $groupRepository,
        ManagerInterface $messageManager,
        ConfigFactory $configFactory,
        CommandPoolInterface $commandPool,
        SelectedStore $selectedStore,
        ScopeConfigInterface $config,
        $methods = []
    ) {
        $this->websiteRepository = $websiteRepository;
        $this->groupRepository = $groupRepository;
        $this->messageManager = $messageManager;
        $this->configFactory = $configFactory;
        $this->commandPool = $commandPool;
        $this->selectedStore = $selectedStore;
        $this->config = $config;
        $this->methods = $methods;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $request = $observer->getRequest();
        $configData = $observer->getData('configData');

        if (empty($configData['section'])) {
            return;
        }

        if ($configData['section'] !== 'payment') {
            return;
        }

        $websiteId = $request->getParam('website');
        $storeId = $request->getParam('store');

        if (empty($storeId) && !empty($websiteId)) {
            $website = $this->websiteRepository->getById($websiteId);
            $storeGroupId = $website->getDefaultGroupId();
            $group = $this->groupRepository->get($storeGroupId);
            $storeId = $group->getDefaultStoreId();
        }

        $this->selectedStore->setStoreId($storeId);

        foreach ($this->methods as $method => $label) {
            /** @var \OnTap\MasterCard\Gateway\Config\Config $config */
            $config = $this->configFactory->create();
            $config->setMethodCode($method);

            $merchantId = $config->getMerchantId($storeId);
            $password = $config->getMerchantPassword($storeId);
            $apiUrl = $config->getApiUrl($storeId);

            $enabled = "1" === $this->config->getValue(
                sprintf('payment/%s/active', $method),
                ($storeId !== null) ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $storeId
            );

            if (!$enabled || !$merchantId || !$password || !$apiUrl) {
                continue;
            }

            try {
                $command = $this->commandPool->get(sprintf('check_gateway_%s', $method));
                $command->execute([]);
                $this->messageManager->addSuccessMessage(__('"%1" test was successful.', __($label)));
            } catch (\Exception $e) {
                $this->messageManager->addWarningMessage(__('There was a problem communicating with "%1": %2', __($label), $e->getMessage()));
            }
        }
    }
}
