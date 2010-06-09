<?php
/**
* Policy_Passthru - SubRosa policy object which restricts blog
*                     resources to only authors on that blog.
*/
class Policy_Passthru extends SubRosa_PolicyAbstract {

    public function is_protected  () { return false; }

    public function is_authorized() {
        # Load user and meta and put into SESSION
        
    }

    public function login_page    ( $params            );
    public function handle_login  ( $fileinfo          );
    public function handle_auth   ( $fileinfo          );
    public function handle_logout ( $fileinfo          );
    public function error_handler ( $errno, $errstr,   
                                       $errfile, $errline );



}

define( 'SUBROSA_POLICY', 'Policy_Passthru' );

?>