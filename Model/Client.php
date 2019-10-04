<?php

namespace SwedbankPay\Core\Model;

use SwedbankPay\Core\Logger\Logger;
use SwedbankPay\Core\Helper\Config;
use PayEx\Api\Client\Exception as RequestException;
use SwedbankPay\Core\Exception\ClientException;

class Client extends \PayEx\Api\Client\Client
{
    /**
     * @var Config $config
     */
    protected $config;

    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * Client constructor.
     * @param Config $config
     * @param Logger $logger
     * @param array $data
     * @throws ClientException
     */
    public function __construct(
        Config $config,
        Logger $logger,
        array $data = []
    ) {
        $this->config = $config;
        $this->logger = $logger;

        if (!$this->config->isActive()) {
            return;
        }

        try {
            parent::__construct($data);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new ClientException($e);
        }

        $errorMessages = [];

        $merchantToken = $this->config->getValue('merchant_token');
        if (!$merchantToken) {
            $errorMessages['merchant_token'] = sprintf('Invalid Merchant Token: %s', $merchantToken);
        }

        $payeeId = $this->config->getValue('payee_id');
        if (!$payeeId) {
            $errorMessages['payee_id'] = sprintf('Invalid Payee ID: %s', $payeeId);
        }

        if (count($errorMessages) > 0) {
            foreach ($errorMessages as $message) {
                $this->logger->error($message);
            }
            throw new ClientException(
                sprintf(
                    'Invalid values for required fields: %s',
                    implode(', ', array_keys($errorMessages))
                )
            );
        }

        $this->setMerchantToken($merchantToken);
        $this->setPayeeId($payeeId);

        try {
            $this->setMode(
                $this->config->getValue('test_mode') ? self::MODE_TEST : self::MODE_PRODUCTION
            );
        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            throw new ClientException($e);
        }
    }

    /**
     * @param $requestMethod
     * @param $requestEndpoint
     * @param array $requestParams
     * @return array|mixed|object
     * @throws ClientException
     */
    public function request($requestMethod, $requestEndpoint, $requestParams = [])
    {
        if (!$this->config->isActive()) {
            return $this;
        }

        try {
            parent::request($requestMethod, $requestEndpoint, $requestParams);
        } catch (\Exception $e) {
            if (!$this->getErrorMessage()) {
                $this->setErrorMessage($e->getMessage());
                $this->setErrorCode($e->getCode());
            }
            $this->logger->error(
                sprintf(
                    'SwedbankPay Client Request Error [%s]: %s',
                    $this->getErrorCode(),
                    $this->getErrorMessage()
                )
            );
        }

        if ($this->config->getValue('debug_mode')) {
            $this->logger->debug($this->getDebugInfo());
        }

        if ($this->getErrorMessage()) {
            throw new ClientException($this->getErrorMessage(), $this->getErrorCode());
        }

        return $this;
    }
}
