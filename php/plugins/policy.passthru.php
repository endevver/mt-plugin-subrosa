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

        $keys = array_merge( array_keys( $user_hash ),
                             array_keys( $meta )      );

        foreach ( $keys as $key ) {
            if ( isset( $user_hash[$key] )) {
                $val = $user_hash[$key];
            }
            elseif ( isset( $meta[$key] )) {
                $val = $meta[$key];
            }
            else {
                $val = '';
            }
            SubRosa_Util::phpsession( $key, $val );
        }
        $mt->log_dump(array(noscreen => 1));
        $this->initialized = 1;
    }

    public function login_page() { }
    public function error_page() { }
}

define( 'SUBROSA_POLICY', 'Policy_Passthru' );

?>