<?php

namespace cusodede\sse\mechanisms;

class FileStorageTechnique extends StorageTechnique
{
    /**
     * @var string
     */
    private $path;

    /**
     * @param array $arguments
     * @return void
     */
    public function __construct(array $arguments)
    {
        if (!array_key_exists('path', $arguments)) {
            throw new \InvalidArgumentException('Key path does not exists in arguments');
        }
        parent::__construct($arguments);

        $this->path = $arguments['path'];
        if (!is_dir($this->path)) {
            mkdir($this->path);
        }
    }

    /**
     * @inheritdoc
     */
    public function get($key)
    {
        $file = $this->getFileName($key);
        if (!file_exists($file)) {
            return null;
        }
        $content = file_get_contents($file);
        $this->gc();
        return $content;
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value)
    {
        $result = file_put_contents($this->path.'/sess_'.sha1($key),$value) === false ? false : true;
        $this->gc();
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function delete($key)
    {
        $path = $this->getFileName($key);
        if(file_exists($path)){
            unlink($path);
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function has($key)
    {
        $file = $this->getFileName($key);
        return file_exists($file);
    }

    /**
     * @return void
     */
    private function gc(){
        if($this->lifetime == 0){
            return;
        }
        foreach(glob($this->path . '/sess_*') as $file){
            if(filemtime($file) + $this->lifetime < time() && file_exists($file)){
                unlink($file);
            }
        }
    }

    /**
     * @param string $key
     * @return string
     */
    protected function getFileName($key)
    {
        return $this->path.'/sess_'.sha1($key);
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }
}