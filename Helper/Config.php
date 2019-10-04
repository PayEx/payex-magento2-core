<?php

namespace SwedbankPay\Core\Helper;

use Magento\Config\Model\Config\PathValidator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\ValidatorException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use SwedbankPay\Core\Logger\Logger;

class Config extends AbstractHelper
{
    const XML_CONFIG_SECTION = 'swedbank_pay';

    const XML_CONFIG_GROUP = 'core';

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var PathValidator
     */
    protected $pathValidator;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var Logger
     */
    protected $logger;

    protected $moduleDependencies = [];

    /**
     * Config constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param PathValidator $pathValidator
     * @param WriterInterface $configWriter
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        PathValidator $pathValidator,
        WriterInterface $configWriter,
        Logger $logger
    ) {
        $this->storeManager  = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->pathValidator = $pathValidator;
        $this->configWriter = $configWriter;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * @param string $module
     * @return mixed|string
     */
    protected function getConfigSection($module = '')
    {
        $configSectionConst = "\\" . get_called_class() . "::XML_CONFIG_SECTION";

        if ($module != '') {
            $moduleConfigHelper = '\\' . str_replace('_', '\\', $module) . '\\Helper\\Config';
            if (defined($moduleConfigHelper . '::XML_CONFIG_SECTION')) {
                $configSectionConst = $moduleConfigHelper . '::XML_CONFIG_SECTION';
            }
        }

        return (defined($configSectionConst)) ? constant($configSectionConst) : self::XML_CONFIG_SECTION;
    }

    /**
     * @param string $module
     * @return string
     */
    protected function getConfigGroup($module = '')
    {
        $configGroupConst = "\\" . get_called_class() . "::XML_CONFIG_GROUP";

        if ($module != '') {
            $moduleConfigHelper = '\\' . str_replace('_', '\\', $module) . '\\Helper\\Config';
            if (defined($moduleConfigHelper . '::XML_CONFIG_GROUP')) {
                $configGroupConst = $moduleConfigHelper . '::XML_CONFIG_GROUP';
            }
        }

        return (defined($configGroupConst)) ? constant($configGroupConst) : 'core';
    }

    /**
     * @param string $code
     * @param string $module
     * @return string
     */
    public function getConfigPath($code = '', $module = '')
    {
        if ($code != '') {
            return $this->getConfigSection($module) . '/' . $this->getConfigGroup($module) . '/' . $code;
        }

        return $this->getConfigSection($module) . '/' . $this->getConfigGroup($module);
    }

    /**
     * @param string $code
     * @param string $module
     * @return string
     */
    public function getPaymentConfigPath($code = '', $module = '')
    {
        if ($code != '') {
            return 'payment/' . $this->getConfigSection($module) . '_' . $this->getConfigGroup($module) . '/' . $code;
        }

        return 'payment/' . $this->getConfigSection($module) . '_' . $this->getConfigGroup($module);
    }

    /**
     * @param string $code
     * @param Store|int|string|null $store
     * @return mixed
     */
    public function getValue($code, $store = null)
    {
        if ($store instanceof Store) {
            $store = $store->getId();
        }

        return $this->scopeConfig->getValue(
            $this->getConfigPath($code),
            $this->getScope($store),
            $store
        );
    }

    /**
     * Get payment config value
     *
     * @param string $code
     * @param Store|int|string|null $store
     * @param string $paymentMethod
     *
     * @return mixed
     */
    public function getPaymentValue($code, $paymentMethod, $store = null)
    {
        if (!$paymentMethod || !$code) {
            return null;
        }

        if ($store instanceof Store) {
            $store = $store->getId();
        }

        return $this->scopeConfig->getValue(
            $this->getPaymentConfigPath($code, $paymentMethod),
            $this->getScope($store),
            $store
        );
    }

    /**
     * @param string $module
     * @return bool
     */
    public function isPayment($module = '')
    {
        $paymentConfigPath = $this->getPaymentConfigPath('active', $module);
        $isPayment = ($this->scopeConfig->isSetFlag($paymentConfigPath) == null) ? false : true;

        return $isPayment;
    }

    /**
     * @param Store|int|string|null $store
     * @param string $module
     * @return bool

     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function isActive($store = null, $module = 'core')
    {
        if ($this->isPayment($module)) {
            $configPath = $this->getPaymentConfigPath('active', $module);
        }

        if (!isset($configPath)) {
            $configPath = $this->getConfigPath('active', $module);
        }

        $isActive = $this->scopeConfig->isSetFlag($configPath, $this->getScope($store), $store);

        if (in_array($this->_getModuleName(), $this->moduleDependencies)) {
            $key = array_search($this->_getModuleName(), $this->moduleDependencies);
            unset($this->moduleDependencies[$key]);
        }

        if (!$isActive || $module != 'core' || count($this->moduleDependencies) == 0) {
            return $isActive;
        }

        foreach ($this->moduleDependencies as $dependency) {
            $isActive = $this->isActive($store, $dependency);

            if (!$isActive) {
                break;
            }

            if ($isActive && $dependency == 'SwedbankPay_Core') {
                $merchantToken = $this->scopeConfig->getValue(
                    $this->getConfigPath('merchant_token', $dependency),
                    $this->getScope($store),
                    $store
                );

                if (trim($merchantToken) == '') {
                    $isActive = false;
                    break;
                }

                $payeeId = $this->scopeConfig->getValue(
                    $this->getConfigPath('payee_id', $dependency),
                    $this->getScope($store),
                    $store
                );

                if (trim($payeeId) == '') {
                    $isActive = false;
                    break;
                }
            }
        }

        return $isActive;
    }

    /**
     * @param string $scope
     * @param Store $store
     * @param string $module
     */
    public function deactivateModule($scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $store = null, $module = '')
    {
        if ($this->isPayment($module)) {
            $configPath = $this->getPaymentConfigPath('active', $module);
        }

        if (!isset($configPath)) {
            $configPath = $this->getConfigPath('active', $module);
        }

        $this->configWriter->save($configPath, 0, $scope, $store);
    }

    /**
     * Get the scope value of the store
     *
     * @param Store $store
     * @return string
     */
    private function getScope($store = null)
    {
        if ($store === null) {
            return ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        }

        return ScopeInterface::SCOPE_STORES;
    }
}
