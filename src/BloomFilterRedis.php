<?php

namespace pengbloom\BloomFilter;

use Exception;
use Illuminate\Support\Facades\Redis;

/**
 * 使用redis实现的布隆过滤器
 */
abstract class BloomFilterRedis
{
    /**
     * 需要使用一个方法来定义bucket的名字
     */
    protected $bucket;

    protected $hashFunction;

    /**
     * @var BloomFilterHash
     */
    private $Hash;
    /**
     * @var mixed|\Redis
     */
    private $Redis;

    public function __construct($config = 'business')
    {
        if (!$this->bucket || !$this->hashFunction) {
            throw new Exception("需要定义bucket和hashFunction", 1);
        }
        $this->Hash = new BloomFilterHash;
        $this->Redis = Redis::connection($config)->client(); //假设这里你已经连接好了
    }

    /**
     * 添加到集合中
     */
    public function add($string)
    {
        $pipe = $this->Redis->multi();
        foreach ($this->hashFunction as $function) {
            $hash = $this->Hash->$function($string);
            var_dump($hash);
            $pipe->setBit($this->bucket, $hash, 1);
        }
        return $pipe->exec();
    }

    /**
     * 查询是否存在, 如果曾经写入过，必定回true，如果没写入过，有一定几率会误判为存在
     */
    public function exists($string): bool
    {
        $pipe = $this->Redis->multi();
        $len = strlen($string);
        foreach ($this->hashFunction as $function) {
            $hash = $this->Hash->$function($string, $len);
            $pipe = $pipe->getBit($this->bucket, $hash);
        }
        $res = $pipe->exec();
        foreach ($res as $bit) {
            if ($bit == 0) {
                return false;
            }
        }
        return true;
    }

}
