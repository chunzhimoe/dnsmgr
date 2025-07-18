<?php

namespace app\lib\dns;

use app\lib\DnsInterface;
use app\lib\client\NS1;

class ns1 implements DnsInterface
{
    private $apiKey;
    private $client;
    private $domain;
    private $domainid;
    private $error;
    private $proxy;

    function __construct($config)
    {
        $this->apiKey = $config['ak'];
        $this->domain = $config['domain'];
        $this->domainid = $config['domainid'];
        $this->proxy = isset($config['proxy']) ? $config['proxy'] == 1 : false;
        $this->client = new NS1($this->apiKey, 'https://api.nsone.net/v1', $this->proxy);
    }

    public function getError()
    {
        return $this->error;
    }

    public function check()
    {
        try {
            $this->client->getZones();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function getDomainList($KeyWord = null, $PageNumber = 1, $PageSize = 20)
    {
        try {
            $zones = $this->client->getZones();
            $list = [];
            $filtered = [];
            
            // 过滤搜索关键词
            if (!empty($KeyWord)) {
                foreach ($zones as $zone) {
                    if (stripos($zone['zone'], $KeyWord) !== false) {
                        $filtered[] = $zone;
                    }
                }
            } else {
                $filtered = $zones;
            }
            
            // 分页处理
            $total = count($filtered);
            $offset = ($PageNumber - 1) * $PageSize;
            $pagedZones = array_slice($filtered, $offset, $PageSize);
            
            foreach ($pagedZones as $zone) {
                $list[] = [
                    'DomainId' => $zone['zone'],
                    'Domain' => $zone['zone'],
                    'RecordCount' => isset($zone['records']) ? count($zone['records']) : 0,
                ];
            }
            
            return ['total' => $total, 'list' => $list];
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function getDomainRecords($PageNumber = 1, $PageSize = 20, $KeyWord = null, $SubDomain = null, $Value = null, $Type = null, $Line = null, $Status = null)
    {
        try {
            $zone = $this->client->getZone($this->domain);
            if (!$zone || !isset($zone['records'])) {
                return ['total' => 0, 'list' => []];
            }
            
            $records = $zone['records'];
            $list = [];
            
            foreach ($records as $record) {
                // 过滤条件
                if (!empty($KeyWord) && stripos($record['domain'], $KeyWord) === false && stripos($record['answers'][0]['answer'][0] ?? '', $KeyWord) === false) {
                    continue;
                }
                if (!empty($SubDomain)) {
                    $expectedDomain = $SubDomain === '@' ? $this->domain : $SubDomain . '.' . $this->domain;
                    if ($record['domain'] !== $expectedDomain) {
                        continue;
                    }
                }
                if (!empty($Value)) {
                    $found = false;
                    foreach ($record['answers'] as $answer) {
                        if (isset($answer['answer'][0]) && stripos($answer['answer'][0], $Value) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) continue;
                }
                if (!empty($Type) && $record['type'] !== strtoupper($Type)) {
                    continue;
                }
                
                // 构建记录数据
                $name = $record['domain'] === $this->domain ? '@' : str_replace('.' . $this->domain, '', $record['domain']);
                $status = '1'; // NS1默认启用
                $ttl = $record['ttl'] ?? 3600;
                $mx = null;
                $weight = null;
                $remark = null;
                
                // 获取记录值
                $value = '';
                if (isset($record['answers'][0]['answer'][0])) {
                    $value = $record['answers'][0]['answer'][0];
                }
                
                // 处理MX记录
                if ($record['type'] === 'MX' && isset($record['answers'][0]['answer'][1])) {
                    $mx = $record['answers'][0]['answer'][0];
                    $value = $record['answers'][0]['answer'][1];
                }
                
                // 处理权重
                if (isset($record['answers'][0]['meta']['weight'])) {
                    $weight = $record['answers'][0]['meta']['weight'];
                }
                
                $list[] = [
                    'RecordId' => $record['id'],
                    'Domain' => $this->domain,
                    'Name' => $name,
                    'Type' => $record['type'],
                    'Value' => $value,
                    'Line' => 'default',
                    'TTL' => $ttl,
                    'MX' => $mx,
                    'Status' => $status,
                    'Weight' => $weight,
                    'Remark' => $remark,
                    'UpdateTime' => null,
                ];
            }
            
            // 分页处理
            $total = count($list);
            $offset = ($PageNumber - 1) * $PageSize;
            $pagedList = array_slice($list, $offset, $PageSize);
            
            return ['total' => $total, 'list' => $pagedList];
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function getSubDomainRecords($SubDomain, $PageNumber = 1, $PageSize = 20, $Type = null, $Line = null)
    {
        return $this->getDomainRecords($PageNumber, $PageSize, null, $SubDomain, null, $Type, $Line);
    }

    public function getDomainRecordInfo($RecordId)
    {
        try {
            $zone = $this->client->getZone($this->domain);
            if (!$zone || !isset($zone['records'])) {
                return false;
            }
            
            foreach ($zone['records'] as $record) {
                if ($record['id'] === $RecordId) {
                    $name = $record['domain'] === $this->domain ? '@' : str_replace('.' . $this->domain, '', $record['domain']);
                    $value = isset($record['answers'][0]['answer'][0]) ? $record['answers'][0]['answer'][0] : '';
                    $mx = null;
                    $weight = null;
                    
                    if ($record['type'] === 'MX' && isset($record['answers'][0]['answer'][1])) {
                        $mx = $record['answers'][0]['answer'][0];
                        $value = $record['answers'][0]['answer'][1];
                    }
                    
                    if (isset($record['answers'][0]['meta']['weight'])) {
                        $weight = $record['answers'][0]['meta']['weight'];
                    }
                    
                    return [
                        'RecordId' => $record['id'],
                        'Domain' => $this->domain,
                        'Name' => $name,
                        'Type' => $record['type'],
                        'Value' => $value,
                        'Line' => 'default',
                        'TTL' => $record['ttl'] ?? 3600,
                        'MX' => $mx,
                        'Status' => '1',
                        'Weight' => $weight,
                        'Remark' => null,
                        'UpdateTime' => null,
                    ];
                }
            }
            return false;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function addDomainRecord($Name, $Type, $Value, $Line = 'default', $TTL = 600, $MX = 1, $Weight = null, $Remark = null)
    {
        try {
            $domain = $Name === '@' ? $this->domain : $Name . '.' . $this->domain;
            $type = strtoupper($Type);
            
            // 构建记录参数
            $params = [
                'zone' => $this->domain,
                'domain' => $domain,
                'type' => $type,
                'ttl' => (int)$TTL,
            ];
            
            // 构建answers数组
            $answer = [];
            if ($type === 'MX') {
                $answer['answer'] = [(int)$MX, $Value];
            } else {
                $answer['answer'] = [$Value];
            }
            
            // 添加权重
            if ($Weight !== null) {
                $answer['meta'] = ['weight' => (int)$Weight];
            }
            
            $params['answers'] = [$answer];
            
            $result = $this->client->createRecord($this->domain, $domain, $type, $params);
            
            if ($result && isset($result['id'])) {
                return $result['id'];
            }
            return false;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $Line = 'default', $TTL = 600, $MX = 1, $Weight = null, $Remark = null)
    {
        try {
            $domain = $Name === '@' ? $this->domain : $Name . '.' . $this->domain;
            $type = strtoupper($Type);
            
            // 构建记录参数
            $params = [
                'zone' => $this->domain,
                'domain' => $domain,
                'type' => $type,
                'ttl' => (int)$TTL,
            ];
            
            // 构建answers数组
            $answer = [];
            if ($type === 'MX') {
                $answer['answer'] = [(int)$MX, $Value];
            } else {
                $answer['answer'] = [$Value];
            }
            
            // 添加权重
            if ($Weight !== null) {
                $answer['meta'] = ['weight' => (int)$Weight];
            }
            
            $params['answers'] = [$answer];
            
            $result = $this->client->updateRecord($this->domain, $domain, $type, $params);
            
            return $result !== false;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function updateDomainRecordRemark($RecordId, $Remark)
    {
        // NS1不支持单独更新备注
        $this->error = 'NS1 does not support updating remarks separately';
        return false;
    }

    public function deleteDomainRecord($RecordId)
    {
        try {
            // 先获取记录信息
            $recordInfo = $this->getDomainRecordInfo($RecordId);
            if (!$recordInfo) {
                return false;
            }
            
            $domain = $recordInfo['Name'] === '@' ? $this->domain : $recordInfo['Name'] . '.' . $this->domain;
            $type = $recordInfo['Type'];
            
            $result = $this->client->deleteRecord($this->domain, $domain, $type);
            return $result !== false;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function setDomainRecordStatus($RecordId, $Status)
    {
        // NS1不支持启用/禁用记录状态
        $this->error = 'NS1 does not support enable/disable record status';
        return false;
    }

    public function getDomainRecordLog($PageNumber = 1, $PageSize = 20, $KeyWord = null, $StartDate = null, $endDate = null)
    {
        // NS1的日志功能需要通过其他API接口实现
        $this->error = 'NS1 record log feature not implemented';
        return false;
    }

    public function getRecordLine()
    {
        return ['default' => 'Default'];
    }

    public function getMinTTL()
    {
        return 1;
    }

    public function addDomain($Domain)
    {
        try {
            $result = $this->client->createZone($Domain);
            return $result !== false;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
}