<?php
// "Properties" in PHP are actually "attributes", that is, simple variables
// without functionality.  This is an abstract class which turns them into
// true properties with implicit accessor and mutator functionality. 
//
// If your class extends this abstract class, you will be able to create
// accessors and mutators that will be called automagically, using php's
// magic methods, when the corresponding property is accessed.
//
abstract class PropertyObject {
    public function __get($name) {
        if (method_exists($this, ($method = 'get_'.$name))) {
            return $this->$method();
        }
        else return;
    }

    public function __isset($name) {
        if (method_exists($this, ($method = 'isset_'.$name))) {
            return $this->$method();
        } else return;
    }

    public function __set($name, $value) {
        if (method_exists($this, ($method = 'set_'.$name))) {
            $this->$method($value);
        }
    }

    public function __unset($name) {
        if (method_exists($this, ($method = 'unset_'.$name))) {
            $this->$method();
        }
    }
}

?>
