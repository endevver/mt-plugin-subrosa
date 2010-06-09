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
    

    public function login_page() { }
    public function error_page() { }
}

define( 'SUBROSA_POLICY', 'Policy_Passthru' );

?>