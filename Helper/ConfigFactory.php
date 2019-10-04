<?php

namespace SwedbankPay\Core\Helper;

use Magento\Framework\ObjectManagerInterface;

class ConfigFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * ConfigFactory constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param string $moduleName
     * @return Config
     */
    public function create($moduleName)
    {
        return $this->objectManager->get('SwedbankPay\\' . $moduleName . '\\Helper\\Config');
    }
}
