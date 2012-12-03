<?php
require_once 'mixi_graph_api.php';

class Mixi extends MixiGraphAPI {
    private $_Store = array();
    function __construct($config, $store )
    {
      parent::__construct($config);
      $this->_Store = $store;
    }

    public function setAppData($key, $value)
    {
        if(!$key) return;
        $name = $this->createKeyname($key);
        $this->_Store[$name] = $value;
    }

    public function getAppData($key, $default = false)
    {
      $name = $this->createKeyname($key);
        return ($this->_Store  && isset($this->_Store[$name])) ? $this->_Store[$name] : $default;
    }

    protected function clearAppData($key)
    {
        $name = $this->createKeyname($key);
        unset($this->_Store[$name]);
    }

    protected static $supportedKeys =
        array('access_token', 'refresh_token', 'user_id', 'scope');

    public function clearAllAppData() {
        foreach (self::$supportedKeys as $key) {
            $this->clearAppData($key);
        }
    }

    protected function createKeyname($key) {
    return implode('_', array('mixi',
                              $this->consumer_key,
                              $key));
    }
  

    public function clearStroe () {
      $this->_Store = array();
    }
    public function clearStore () {
      $this->_Store = array();
    }
    public function getStore() {
      return $this->_Store;
    }
}
