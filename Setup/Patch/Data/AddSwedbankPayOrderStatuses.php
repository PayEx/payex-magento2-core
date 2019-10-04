<?php

namespace SwedbankPay\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Class AddSwedbankPayOrderStatuses
 */
class AddSwedbankPayOrderStatuses implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * AddSwedbankPayOrderStates constructor.
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        /**
         * Prepare database for install
         */
        $this->moduleDataSetup->getConnection()->startSetup();

        $data = [];
        $statuses = [
            'swedbank_pay_pending' => __('SwedbankPay Pending'),
            'swedbank_pay_reversed' => __('SwedbankPay Reversed'),
            'swedbank_pay_cancelled_reversal'  => __('SwedbankPay Cancelled Reversal'),
        ];

        foreach ($statuses as $code => $info) {
            $data[] = ['status' => $code, 'label' => $info];
        }

        $this->moduleDataSetup->getConnection()->insertArray(
            $this->moduleDataSetup->getTable('sales_order_status'),
            ['status', 'label'],
            $data
        );

        /**
         * Prepare database after install
         */
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
