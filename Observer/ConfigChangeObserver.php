<?php

namespace SwedbankPay\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

use SwedbankPay\Core\Helper\Config as CoreConfig;
use SwedbankPay\Core\Helper\ConfigFactory;
use SwedbankPay\Core\Logger\Logger;

class ConfigChangeObserver implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CoreConfig
     */
    protected $coreConfig;

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var bool
     */
    private static $isRunning = false;

    /**
     * ConfigChangeObserver constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param CoreConfig $coreConfig
     * @param ConfigFactory $configFactory
     * @param Logger $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CoreConfig $coreConfig,
        ConfigFactory $configFactory,
        Logger $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->coreConfig = $coreConfig;
        $this->configFactory = $configFactory;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @throws ValidatorException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
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

        $changedPaths = (array)$observer->getData('changed_paths');

        $activeChangePaths = array_filter($changedPaths, function ($var) {
            return (
                preg_match('|swedbank_pay/[^/]+/active|', $var) ||
                preg_match('|payment/swedbank_pay_[^/]+/active|', $var)
            );
        });

        if (count($activeChangePaths) == 0) {
            return;
        }

        $deactivated = [];

        foreach ($activeChangePaths as $changePath) {
            $isActivated = $this->scopeConfig->getValue(
                $changePath,
                $scope,
                $scopeId
            );

            $moduleConfigGroup = '';

            if (strpos($changePath, 'payment/swedbank_pay_') !== false) {
                $moduleConfigGroup = substr(
                    $changePath,
                    strlen('payment/swedbank_pay_'),
                    0 - strlen('/active')
                );
            }

            if ($moduleConfigGroup == '') {
                $moduleConfigGroup = substr(
                    $changePath,
                    strlen('swedbank_pay/'),
                    0 - strlen('/active')
                );
            }

            $moduleNameParts = explode('_', $moduleConfigGroup);
            $moduleNameParts = array_map('ucfirst', $moduleNameParts);
            $moduleName = implode('', $moduleNameParts);

            $moduleConfigHelper = $this->configFactory->create($moduleName);

            $isValid = $moduleConfigHelper->isActive($store);

            if ($isActivated && !$isValid) {
                $moduleConfigHelper->deactivateModule($scope, $scopeId);
                $deactivated[$changePath] = implode(' ', $moduleNameParts);
            }
        }

        self::$isRunning = false;

        if (count($deactivated) > 0) {
            throw new ValidatorException(
                __('Unable to activate SwedbankPay module(s): ' . implode(', ', $deactivated) . '. ' .
                'Please make sure the SwedbankPay API Credentials and any other required settings are correct.')
            );
        }
    }
}
