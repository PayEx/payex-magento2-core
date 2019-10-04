<?php
namespace SwedbankPay\Core\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Debug extends Base
{
    protected $loggerType = Logger::DEBUG;
    protected $fileName = '/var/log/swedbank_pay_debug.log';
}
