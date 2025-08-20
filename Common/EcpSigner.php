<?php

namespace Ecommpay\Payments\Common;

use ecommpay\SignatureHandler;

class EcpSigner extends SignatureHandler
{
    /**
     * @var EcpConfigHelper
     */
    private $configHelper;

    public function __construct(EcpConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;
        parent::__construct($configHelper->getSecretKeyDecrypted());
    }

    public function checkSignature(array $data): bool
    {
        if (!isset($data['signature'])) {
            return false;
        }

        $signature = $data['signature'];
        unset($data['signature']);

        return $this->check($data, $signature);
    }

    public function getSignature(array $data, array $ignoredParams = []): string
    {
        foreach ($data as $key => $_) {
            if (in_array($key, $ignoredParams, true)) {
                unset($data[$key]);
            }
        }

        return $this->sign($data);
    }


    public function unsetNullParams(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
