<?php
require_once( 'SubRosa/PolicyAbstract.php' );

/**
* Policy_Passthru - SubRosa policy object which restricts blog
*                     resources to only authors on that blog.
*/
class Policy_Passthru extends SubRosa_PolicyAbstract {

    function __construct() {
        global $mt;
        $mt->init_auth();
    }

    public function is_protected()  {
        return false;
    }

    public function is_authorized() {
        return true;
    }

    public function login_page() { }
    public function error_page() { }
}

define( 'SUBROSA_POLICY', 'Policy_Passthru' );

?>