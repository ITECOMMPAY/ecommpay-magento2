<?php

namespace Ecommpay\Payments\Block\Adminhtml\Order;

class OrderView extends \Magento\Backend\Block\Template
{
    public function testFunction()
    {
        return "Test Payment Info";
    }
    protected $_template = 'Ecommpay_Payments::order/order_view.phtml';
}