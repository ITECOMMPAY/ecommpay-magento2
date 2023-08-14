<?php

namespace Ecommpay\Payments\Common;

class EcpSigner
{
    /**
     * @param array $params
     * @param array $ignoreParamKeys
     * @param int $currentLevel
     * @param string $prefix
     * @return array
     */
    private function getParamsToSign(array $params, array $ignoreParamKeys = [], $currentLevel = 1, $prefix = '')
    {
        $paramsToSign = [];
        foreach ($params as $key => $value) {
            if (str_starts_with($key, "_")) {
                continue;
            }
            if (is_null($value)){
                continue;
            }
            if ((in_array($key, $ignoreParamKeys) && $currentLevel == 1)) {
                continue;
            }
            $paramKey = ($prefix ? $prefix . ':' : '') . $key;
            if (is_array($value)) {
                if ($currentLevel >= 3) {
                    $paramsToSign[$paramKey] = (string)$paramKey . ':';
                } else {
                    $subArray = $this->getParamsToSign($value, $ignoreParamKeys, $currentLevel + 1, $paramKey);
                    $paramsToSign = array_merge($paramsToSign, $subArray);
                }
            } else {
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } else {
                    $value = (string)$value;
                }
                $paramsToSign[$paramKey] = (string)$paramKey . ':' . $value;
            }
        }
        if ($currentLevel == 1) {
            ksort($paramsToSign, SORT_NATURAL);
        }
        return $paramsToSign;
    }

    /**
     * @param array $data
     * @return string
     */
    public function getSignature(array $data)
    {
        $paramsToSign = $this->getParamsToSign($data);
        $stringToSign = $this->getStringToSign($paramsToSign);
        $secretKey = EcpConfigHelper::getInstance()->getSecretKeyDecrypted();
        return base64_encode(hash_hmac('sha512', $stringToSign, $secretKey, true));
    }

    /**
     * @param array $paramsToSign
     * @return string
     */
    private function getStringToSign(array $paramsToSign)
    {
        return implode(';', $paramsToSign);
    }

    /**
     * @param array $data
     * @return bool
     */
    public function checkSignature(array $data)
    {
        if (!array_key_exists('signature', $data)) {
            return false;
        }
        $signature = $data['signature'];
        unset($data['signature']);

        return $this->getSignature($data) === $signature;
    }
}