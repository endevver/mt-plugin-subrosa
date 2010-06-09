<?php
require_once( 'SubRosa/PolicyAbstract.php' );

/**
* Policy_Passthru - SubRosa policy object which restricts blog
*                     resources to only authors on that blog.
*/
class Policy_Passthru extends SubRosa_PolicyAbstract {

    private $initialized = 0;
    
    public function is_protected()  { $this->get_auth(); return false; }
    public function is_authorized() { $this->get_auth(); return true;  }
    
    private function get_auth() {

        if ( $this->initialized ) return;

        # Load user and meta and put into SESSION
        global $mt;
        $mt->marker('In is_authorized, '.__FILE__.', line '.__LINE__);
        $auth      =  $mt->init_auth();
        $user      =& $auth->user();
        $user_hash =  $user->property_hash();
        $meta      =  $mt->db->get_meta( 'author', $user->get( 'id' ));

        $mt->log('$user_hash: '.print_r( $user_hash, true ));
        $mt->log('$meta: '.print_r( $meta, true ));

        $mt->log_dump(array(noscreen => 1));
        
        # Used array union operator to merge $user_hash and 
        # $meta while favoring the former if any keys conflict.
        foreach (( $user_hash + $meta ) as $key => $val ) {
            SubRosa_Util::phpsession( $key, $val );
        }
        $this->initialized = 1;
    }

    public function login_page() { }
    public function error_page() { }
}

define( 'SUBROSA_POLICY', 'Policy_Passthru' );

?>