<?php

namespace app\lib\client;

use Exception;

/**
 * IBM NS1 Connect API Client
 */
class NS1
{
    private $apiKey;
    private $endpoint;
    private $proxy = false;

    public function __construct($apiKey, $endpoint = 'https://api.nsone.net/v1', $proxy = false)
    {
        $this->apiKey = $apiKey;
        $this->endpoint = rtrim($endpoint, '/');
        $this->proxy = $proxy;
    }

    /**
     * 发送HTTP请求
     * @param string $method HTTP方法
     * @param string $path API路径
     * @param array $params 请求参数
     * @param array $headers 额外的请求头
     * @return array
     * @throws Exception
     */
    public function request($method, $path, $params = [], $headers = [])
    {
        $url = $this->endpoint . '/' . ltrim($path, '/');
        
        $defaultHeaders = [
            'X-NSONE-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DNSMgr/1.0 NS1-PHP-Client');
        
        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        
        switch (strtoupper($method)) {
            case 'GET':
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($params)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($params)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if (!empty($params)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                }
                break;
            default:
                throw new Exception('Unsupported HTTP method: ' . $method);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = 'HTTP Error ' . $httpCode;
            if (isset($data['message'])) {
                $errorMessage .= ': ' . $data['message'];
            } elseif (isset($data['error'])) {
                $errorMessage .= ': ' . $data['error'];
            }
            throw new Exception($errorMessage);
        }
        
        return $data;
    }

    /**
     * 获取所有区域
     * @return array
     */
    public function getZones()
    {
        return $this->request('GET', '/zones');
    }

    /**
     * 获取指定区域信息
     * @param string $zone 区域名称
     * @return array
     */
    public function getZone($zone)
    {
        return $this->request('GET', '/zones/' . $zone);
    }

    /**
     * 创建区域
     * @param string $zone 区域名称
     * @param array $params 区域参数
     * @return array
     */
    public function createZone($zone, $params = [])
    {
        $data = array_merge(['zone' => $zone], $params);
        return $this->request('PUT', '/zones/' . $zone, $data);
    }

    /**
     * 删除区域
     * @param string $zone 区域名称
     * @return array
     */
    public function deleteZone($zone)
    {
        return $this->request('DELETE', '/zones/' . $zone);
    }

    /**
     * 获取区域记录
     * @param string $zone 区域名称
     * @param string $domain 域名
     * @param string $type 记录类型
     * @return array
     */
    public function getRecord($zone, $domain, $type)
    {
        return $this->request('GET', '/zones/' . $zone . '/' . $domain . '/' . $type);
    }

    /**
     * 创建记录
     * @param string $zone 区域名称
     * @param string $domain 域名
     * @param string $type 记录类型
     * @param array $params 记录参数
     * @return array
     */
    public function createRecord($zone, $domain, $type, $params)
    {
        return $this->request('PUT', '/zones/' . $zone . '/' . $domain . '/' . $type, $params);
    }

    /**
     * 更新记录
     * @param string $zone 区域名称
     * @param string $domain 域名
     * @param string $type 记录类型
     * @param array $params 记录参数
     * @return array
     */
    public function updateRecord($zone, $domain, $type, $params)
    {
        return $this->request('POST', '/zones/' . $zone . '/' . $domain . '/' . $type, $params);
    }

    /**
     * 删除记录
     * @param string $zone 区域名称
     * @param string $domain 域名
     * @param string $type 记录类型
     * @return array
     */
    public function deleteRecord($zone, $domain, $type)
    {
        return $this->request('DELETE', '/zones/' . $zone . '/' . $domain . '/' . $type);
    }
}