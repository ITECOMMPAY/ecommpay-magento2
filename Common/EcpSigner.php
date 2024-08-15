<?php

namespace Ecommpay\Payments\Common;

class EcpSigner
{
    private EcpConfigHelper $configHelper;

    public function __construct(EcpConfigHelper $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     *
     * @param array $params
     * @param array $ignoreParamKeys
     * @param int $currentLevel
     * @param string $prefix
     * @return array
     */
    private function getParamsToSign(
        array $params,
        array $ignoreParamKeys = [],
        string $prefix = '',
        bool $sort = true
    ): array {
        $paramsToSign = [];
        foreach ($params as $key => $value) {
            if (substr($key, 0, 1) === "_") {
                continue;
            }
            if ($value === null) {
                continue;
            }
            if (in_array($key, $ignoreParamKeys, true)) {
                continue;
            }
            $paramKey = ($prefix ? $prefix . ':' : '') . $key;
            if (is_array($value)) {
                $subArray = $this->getParamsToSign($value, $ignoreParamKeys, $paramKey, false);
                $paramsToSign = array_merge($paramsToSign, $subArray);
            } else {
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } else {
                    $value = (string)$value;
                }
                $paramsToSign[$paramKey] = $paramKey . ':' . $value;
            }
        }

        if ($sort) {
            ksort($paramsToSign, SORT_NATURAL);
        }
        return $paramsToSign;
    }

    /**
     *
     * @param array $data
     * @param array $ignoredParams
     * @return string */
    public function getSignature(array $data, array $ignoredParams = [])
    {
        $paramsToSign = $this->getParamsToSign($data, $ignoredParams);
        $stringToSign = $this->getStringToSign($paramsToSign);
        $secretKey = $this->configHelper->getSecretKeyDecrypted();
        return base64_encode(hash_hmac('sha512', $stringToSign, $secretKey, true));
    }

    /**
     *
     * @param array $paramsToSign
     * @return string */
    private function getStringToSign(array $paramsToSign)
    {
        return implode(';', $paramsToSign);
    }

    /**
     *
     * @param array $data
     * @return bool */
    public function checkSignature(array $data)
    {
        if (!array_key_exists('signature', $data)) {
            return false;
        }
        $signature = $data['signature'];
        unset($data['signature']);

        return $this->getSignature($data) === $signature;
    }

    /**
     * Unset null parameters
     * @param array $data
     * @return array
     */
    public function unsetNullParams(array $data) {
        foreach ($data as $key => $value) {
            switch (true) {
                case $value === null:
                case is_string($value) && strlen(trim($value)) <= 0:
                    unset($data[$key]);
                    break;
            }
        }
        return $data;
    }
}
