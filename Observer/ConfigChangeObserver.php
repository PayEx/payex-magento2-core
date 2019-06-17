<?php

namespace PayEx\Core\Observer;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

use PayEx\Core\Helper\Config as CoreConfig;
use PayEx\Core\Logger\Logger;

class ConfigChangeObserver implements ObserverInterface
{
    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var CoreConfig */
    protected $coreConfig;

    /** @var Logger */
    protected $logger;

    /** @var bool */
    private static $isRunning = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CoreConfig $coreConfig,
        Logger $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->coreConfig = $coreConfig;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @throws ValidatorException
     */
    public function execute(Observer $observer)
    {
        if (self::$isRunning) {
            return;
        }

        self::$isRunning = true;

        $store = (int)$observer->getData('store');
        $website = (int)$observer->getData('website');

        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = 0;

        if ($website) {
            $scope = ScopeInterface::SCOPE_WEBSITES;
            $scopeId = $website;
        }

        if ($store) {
            $scope = ScopeInterface::SCOPE_STORES;
            $scopeId = $store;
        }

        $changedPaths = $observer->getData('changed_paths');

        $payexActiveChangePaths = array_filter($changedPaths, function ($var) {
            return (
                preg_match('|payex/[^/]+/active|', $var) ||
                preg_match('|payment/payex_[^/]+/active|', $var)
            );
        });

        if (count($payexActiveChangePaths) == 0) {
            return;
        }

        $deactivated = [];

        foreach ($payexActiveChangePaths as $changePath) {
            $isActivated = $this->scopeConfig->getValue(
                $changePath,
                $scope,
                $scopeId
            );

            $moduleConfigGroup = '';

            if (strpos($changePath, 'payment/payex_') !== false) {
                $moduleConfigGroup = substr(
                    $changePath,
                    strlen('payment/payex_'),
                    0 - strlen('/active')
                );
            }

            if ($moduleConfigGroup == '') {
                $moduleConfigGroup = substr(
                    $changePath,
                    strlen('payex/'),
                    0 - strlen('/active')
                );
            }

            $moduleNameParts = explode('_', $moduleConfigGroup);
            $moduleNameParts = array_map('ucfirst', $moduleNameParts);
            $moduleName = implode('', $moduleNameParts);

            $objectManager = ObjectManager::getInstance();
            $moduleConfigHelper = $objectManager->get('PayEx\\' . $moduleName . '\\Helper\\Config');

            $isValid = $moduleConfigHelper->isActive($store);

            if ($isActivated && !$isValid) {
                $moduleConfigHelper->deactivateModule($scope, $scopeId);
                $deactivated[$changePath] = implode(' ', $moduleNameParts);
            }
        }

        self::$isRunning = false;

        if (count($deactivated) > 0) {
            throw new ValidatorException(
                __('Unable to activate PayEx module(s): ' . implode(', ', $deactivated) . '. ' .
                'Please make sure the PayEx Client and any other required settings are correct.')
            );
        }
    }
}
