<?php
require_once( 'SubRosa/PolicyAbstract.php' );

/**
* Policy_Passthru - SubRosa policy object which restricts blog
*                     resources to only authors on that blog.
*/
class Policy_Passthru extends SubRosa_PolicyAbstract {

    public function is_protected()  {
        global $mt;
        $mt->get_auth();
        return false;
    }

    public function is_authorized() {
        global $mt;
        $mt->get_auth();
        return true;
    }

    public function login_page() { }
    public function error_page() { }
}

define( 'SUBROSA_POLICY', 'Policy_Passthru' );

?>