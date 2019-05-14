<?php

namespace PayEx\Core\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Exception extends Base
{
    protected $loggerType = Logger::INFO;
    protected $fileName = '/var/log/payex_error.log';
}
