<?php

namespace SwedbankPay\Core\Model;

use PayEx\Framework\AbstractDataTransferObject;
use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Exception\ServiceException;
use PayEx\Api\Service\Data\RequestInterface;

class Service
{
    /**
     * @var Client $client
     */
    protected $client;

    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * Client constructor.
     * @param Client $client
     * @param Logger $logger
     */
    public function __construct(
        Client $client,
        Logger $logger
    ) {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @param string $service
     * @param string $operation
     * @param AbstractDataTransferObject|null $dataTransferObject
     * @return RequestInterface|string
     * @throws ServiceException
     */
    public function init($service = '', $operation = '', $dataTransferObject = null)
    {
        $errorMessages = [];
        $serviceNamespace = '';

        if ($service == '') {
            $errorMessages['service'] = sprintf("Invalid service '%s'.", $service);
        }

        if ($operation == '') {
            $errorMessages['operation'] = sprintf("Invalid operation '%s'.", $service);
        }

        if (count($errorMessages) == 0) {
            $service = implode('\\', array_map([$this, 'camelCaseStr'], explode('/', $service)));
            $operation = $this->camelCaseStr($operation);
            $serviceNamespace = "\\PayEx\\Api\\Service\\{$service}\\Request\\{$operation}";

            if (!class_exists($serviceNamespace)) {
                $errorMessages['undefined'] = sprintf('Undefined service request class %s', $serviceNamespace);
            }
        }

        if (count($errorMessages) > 0) {
            foreach ($errorMessages as $message) {
                $this->logger->error('Service Init Error: ' . $message);
            }
            throw new ServiceException(sprintf(
                'Service Init Error: %s',
                implode(', ', array_keys($errorMessages))
            ));
        }

        /** @var RequestInterface $service */
        $service = new $serviceNamespace($dataTransferObject);
        $service->setClient($this->client);

        return $service;
    }

    private function camelCaseStr($string = '')
    {
        return implode('', array_map('ucfirst', preg_split('/[^a-z0-9]+/i', $string)));
    }
}
