<?php

namespace cusodede\sse\mechanisms;

use cusodede\sse\DataStorageInterface;

abstract class StorageTechnique implements DataStorageInterface
{

    /**
     * Seconds of inactive timeout
     * @var int
     */
    protected $lifetime = 6000;

    /**
     * @param array $parameter
     * @return void
     */
    public function __construct(array $parameter)
    {
        if (array_key_exists('gc_lifetime', $parameter)) {
            $this->lifetime = $parameter['gc_lifetime'];
        }
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * @param string $name
     */
    public function __unset($name)
    {
        $this->delete($name);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        $value = $this->get($key);
        return !!$value;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return $this->has($key);
    }
}