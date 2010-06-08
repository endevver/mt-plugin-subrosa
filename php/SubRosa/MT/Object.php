<?php
/**
* MT_Object - Generic object class for dynamic MT
*/
class MT_Object
{

    function __construct($data=null) {
        global $mt;
        $this->mt = $mt;
        $this->mt->marker();

        if (isset($data)) {
            $this->init($data);
            return $this;
        }
    }

    function init($data=null) {
        $this->mt->marker();
        foreach ($this->properties as $key) {
            $this->_properties[$key] = null;
        }
        if (isset($data)) {
            $this->from_hash($data);
        }
    }

    function set($var, $input) {
        $this->_properties[$var] = $input;
    }

    function get($var) {
        if (isset($this->_properties[$var])) {
            return $this->_properties[$var];
        }
    }

    function set_defaults($default=array()) {
        foreach ($default as $key) {
            if (is_null($this->get($key))) {
                $this->set($key, $default[$key]);
            }
        }
    }
    
    function print_methods() 
    {
        $arr = get_class_methods(get_class($this));
        foreach ($arr as $method) {
            echo "\tfunction $method()\n";
        }
    }

    function property_hash() {
        return $this->_properties;
    }

    function from_hash($hash) {
        $prefix = $this->class_prefix.'_';
        foreach ($hash as $key => $val) {
            if ($key != 'mt') {
                $key = str_replace($prefix, '', $key);
                $this->set($key, $val);
            }
        }
    }

    function to_hash() {
        foreach (get_object_vars($this) as $key => $val) {
            if ($key != 'mt') { $hash[$key] = $val; }            
        } 
        return $hash;
    }


    function log($msg) { $this->mt->log($msg); }
}

?>