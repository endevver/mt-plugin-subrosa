<?php
/**
* SubRosaPolicy - Generic policy object class for SubRosa
*/
abstract class SubRosaPolicy
{

    abstract public function is_authorized ( );
    abstract public function is_protected  ( );
    abstract public function login_page    ( $params            );
    abstract public function handle_login  ( $fileinfo          );
    abstract public function handle_auth   ( $fileinfo          );
    abstract public function handle_logout ( $fileinfo          );
    abstract public function login_page    ( $params            );
    abstract public function error_handler ( $errno, $errstr,   
                                                $errfile, $errline );
}

?>