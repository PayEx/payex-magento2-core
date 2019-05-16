<?php

namespace PayEx\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Config extends AbstractHelper
{
    protected $storeManager;
    protected $objectManager;
    protected $scopeConfig;

    const XML_CONFIG_SECTION = 'payex';

    /**
     * Config constructor.
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->objectManager = $objectManager;
        $this->storeManager  = $storeManager;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    /**
     * @return string
     */
    protected function getConfigGroup()
    {
        $constant = "\\" . get_called_class() . "::XML_CONFIG_GROUP";
        return (defined($constant)) ? constant($constant) : 'core';
    }

    /**
     * @param $code
     * @return string
     */
    public function getConfigPath($code = '')
    {
        if ($code != '') {
            return self::XML_CONFIG_SECTION . '/' . $this->getConfigGroup() . '/' . $code;
        }

        return self::XML_CONFIG_SECTION . '/' . $this->getConfigGroup();
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
            ScopeInterface::SCOPE_STORE,
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
            sprintf('payment/' . $paymentMethod . '/%s', $code),
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param Store|int|string|null $store
     * @return bool
     */
    public function isActive($store = null)
    {
        return $this->getValue('active', $store) ? true : false;
    }
}
