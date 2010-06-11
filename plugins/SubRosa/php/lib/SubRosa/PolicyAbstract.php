<?php
/**
* SubRosa_PolicyAbstract - Abstract policy class for SubRosa
*/
abstract class SubRosa_PolicyAbstract
{

    abstract public function is_authorized ( );
    abstract public function is_protected  ( );
    abstract public function login_page    ( );
    abstract public function error_page    ( );

    // public function error_handler ( $errno, $errstr, $errfile, $errline );
}

?>