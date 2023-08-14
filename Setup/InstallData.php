<?php

namespace Ecommpay\Payments\Setup;

use Ecommpay\Payments\Common\EcpRefundProcessor;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /**
         * Prepare database for install
         */
        $setup->startSetup();

        $statuses = [
            EcpRefundProcessor::ORDER_STATUS_PARTIAL_REFUND => __('Partial refund'),
            EcpRefundProcessor::ORDER_STATUS_FULL_REFUND => __('Full refund'),
        ];
        foreach ($statuses as $code => $info) {
            $data = ['status' => $code, 'label' => $info];
            $setup->getConnection()->insertOnDuplicate($setup->getTable('sales_order_status'), $data, ['status', 'label']);
        }

        /**
         * Prepare database after install
         */
        $setup->endSetup();
    }
}
