<?php
namespace PayEx\Core\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

use PayEx\Client\Exception\ClientException;

class Error extends Base
{
    protected $fileName = '/var/log/payex_error.log';
    protected $loggerType = Logger::ERROR;
    protected $exceptionHandler;

    public function __construct(
        DriverInterface $filesystem,
        ClientException $exceptionHandler,
        $filePath = null
    ) {
        $this->exceptionHandler = $exceptionHandler;
        parent::__construct($filesystem, $filePath);
    }
}
