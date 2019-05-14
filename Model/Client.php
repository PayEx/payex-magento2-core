<?php

namespace PayEx\Client\Model;

use PayEx\Core\Logger\Logger;
use PayEx\Client\Helper\Config;
use PayEx\Api\Client\Exception as RequestException;
use PayEx\Client\Exception\ClientException;

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

        try {
            parent::__construct($data);
        } catch (RequestException $e) {
            $this->logger->error($e->getMessage());
            throw new ClientException($e);
        }

        $errorMessages = [];

        if (!($merchantToken = $this->config->getValue('merchant_token'))) {
            $errorMessages['merchant_token'] = sprintf('Invalid Merchant Token: %s', $merchantToken);
        }

        if (!($payeeId = $this->config->getValue('payee_id'))) {
            $errorMessages['payee_id'] = sprintf('Invalid Payee ID: %s', $payeeId);
        }

        if (count($errorMessages) > 0) {
            foreach ($errorMessages as $message) {
                $this->logger->error($message);
            }
            throw new ClientException(
                sprintf('Invalid values for required fields: %s',
                implode(', ', array_keys($errorMessages)))
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
        try {
            parent::request($requestMethod, $requestEndpoint, $requestParams);
        } catch (\Exception $e) {
            if (!$this->getErrorMessage()) {
                $this->setErrorMessage($e->getMessage());
                $this->setErrorCode($e->getCode());
            }
            $this->logger->error(
                sprintf(
                    'PayEx Client Request Error [%s]: %s',
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
