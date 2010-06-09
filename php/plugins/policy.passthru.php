<?php
/**
* Policy_Passthru - SubRosa policy object which restricts blog
*                     resources to only authors on that blog.
*/
class Policy_Passthru extends SubRosa_PolicyAbstract {

    public function is_protected  () { return false; }

    public function is_authorized() {
        # Load user and meta and put into SESSION
        global $mt;
        $auth      =  $mt->init_auth();
        $user      =& $auth->user();
        $user_hash =  $user->property_hash();
        $meta      =  $mt->db->get_meta( 'author', $user->get( 'id' ));

        # Used array union operator to merge $user_hash and 
        # $meta while favoring the former if any keys conflict.
        foreach (( $user_hash + $meta ) as $key => $val ) {
            SubRosa_Util::phpsession($key, $val);
        }

        return true;
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