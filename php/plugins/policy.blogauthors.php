<?php
/**
* PolicyBlogAuthors - SubRosa policy object which restricts blog
*                     resources to only authors on that blog.
*/
class Policy_BlogAuthors extends SubRosa_PolicyAbstract {

    public function is_protected  ( );
    public function login_page    ( $params            );
    public function handle_login  ( $fileinfo          );
    public function handle_auth   ( $fileinfo          );
    public function handle_logout ( $fileinfo          );
    public function login_page    ( $params            );
    public function error_handler ( $errno, $errstr,   
                                       $errfile, $errline );

    public function is_authorized ( );
    function is_authorized() {
        $this->is_authorized = 1;
        return;
    }


}

define( 'SUBROSA_POLICY', 'PolicyBlogAuthors' );

?>