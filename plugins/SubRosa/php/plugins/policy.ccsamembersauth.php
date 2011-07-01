<?php
require_once( 'SubRosa/PolicyAbstract.php' );

/**
* Policy_CCSAAuth - SubRosa policy object which restricts blog
*                   resources to only authors on that blog.
*/
class Policy_CCSAMembersAuth extends SubRosa_PolicyAbstract {

    var $request;
    var $is_asset_request = 0;
    var $url_data;
    var $entry;
    var $entries = array();    
    var $access_level = array(
        // FIXME CCSA Staff value does not exist!
        'CCSA Staff'                => 3, 
        'Members Only (no vendors)' => 2,
        'Members Only'              => 1,
        'Public'                    => 0,
    );
    var $force_is_authorized;
    var $request_access_type;

    function __construct() {
        global $mt;
        $mt->init_auth();
    }

    /**
     * check_request - An simple alias for is_authorized()
     *
     * @access  public
     * @param   int     $entry_id
     * @return  bool
     **/
    public function check_request( $entry_id=null ) {
        return $this->is_authorized( $entry_id );
    } // end func check_request


    /**
     * is_authorized - Checks authorization of current request
     *
     * This function inspects the incoming request to divine whether
     * the currently logged in user (if any) has the necessary permission
     * to access the page or document being requested.
     *
     * @access  public
     * @param   int     $entry_id
     * @global  SubRosa $_GLOBALS['mt']
     * @return  bool
     **/
    public function is_authorized( $entry_id=null ) {
        global $mt;
        $mt->marker("In is_authorized with entry_id $entry_id, ".__FILE__);
        error_log('in is_authorized: '.__FILE__);
        /**
         * This begins evaluation of the CCSA authorization business logic
         */
        
        // USER AUTHENTICATION
        // Resolve the current user to test for authorization
        // The user must be authenticated to view protected content
        $user =& $this->resolve_user();
        if ( ! isset($user) ) {
            $mt->marker(  'NOT AUTHORIZED: User must be authenticated');
            error_log('NOT AUTHORIZED: User must be authenticated: '.__FILE__);
            
            return $this->not_authorized();
        }

        // Retrieve the assoc array of CCSA-specific data
        $u_ccsa = $user->get('ccsa'); 
        // $mt->marker( 'Current user data: '.print_r($user, true) );
        
        // EMPLOYEE STATUS
        $is_employee = false;
        if ($u_ccsa['type']=='E') {
            $is_employee = true;
        }
        
        // ACTIVE USER STATUS
        // Protected documents require an active status
        $is_active = $u_ccsa['is_active'];
        
        if ( $is_employee || $is_active ) {
            $mt->marker('AUTHORIZED: User is employee or active: ' 
                         . $u_ccsa['status']);
            error_log('AUTHORIZED: User is employee or active: '.$u_ccsa['status']);
            return true;
        } else {
            $mt->marker('NOT AUTHORIZED: User is not employee or active: '
                        . $u_ccsa['status']);
            error_log('NOT AUTHORIZED: User is not employee or active: '.$u_ccsa['status']);
            return $this->not_authorized();
        }
                
    } // end func is_authorized

    /**
     * resolve_user - Returns the user object for the current user with
     *                both native MT fields and CCSA-specific metadata
     *
     * LONG DESCRIPTION
     *
     * @access  public
     * @global  SubRosa $_GLOBALS['mt']
     * @return  object|null
     **/
    public function resolve_user() {
        global $mt;

        // The designated company/institution code for CCSA
        $staff_id  = $mt->config('imisadminaccountid');

        // Active statuses are:  A (Active) and CM (Complimentary)
        $active_statuses = array( 'A', 'CM' );

        // Load the user from auth cookie information
        $user =& $mt->auth->user();
        if ( ! isset( $user )) return;

        // The user's associated company/institution code
        $u_ccsa_id = $user->get('field.private_ccsa_company_id');

        // The user's member type, e.g. 'V' is for vendor
        $u_type    = $user->get('field.private_ccsa_member_type');

        // The user's CCSA membership status
        $u_status  = $user->get('field.private_ccsa_member_status');

        // Store the CCSA-specific data in user's properties as an
        // assoc. array namespaced by the key "ccsa" to prevent
        // conflicts with native user fields
        $user->set(
            'ccsa', 
            array(
                'type'      => $u_type,
                'is_vendor' => ( $u_type == 'V' ),
                'status'    => $u_status,
                'is_staff'  => ( $u_ccsa_id == $staff_id ),
                'is_active' => in_array( $u_status, $active_statuses ),
            )
        );
        return $user;
    }

    /**
     * not_authorized - A request response function used to deny a request
     *
     * @access  public
     * @global  SubRosa $_GLOBALS['mt']
     * @return string|false
     **/
    public function not_authorized() {
        global $mt;
        $mt->marker('NOT AUTHORIZED!!');
        $user =& $mt->auth->user;  // Can be null if not auth'd'
        if ( $this->is_asset_request ) {
            return isset($user) ? error_page() : login_page();
        }
        else {
            error_log('NOT AUTHORIZED: redirecting to login...');
            // return login_page();
        }
    } // end func not_authorized


    /**
     * login_page - A request response function called for an unauthenticated
     *              user to direct them to a login page.
     *
     * @access  public
     **/
    public function login_page() {
        $url_data =& $this->url_data;
        // FIXME We're not populating $this->entry anymore
        if ( isset($this->entry) &&  isset($url_data['fileinfo'])) {
            header( 'Location: '
                   .$url_data['fileinfo']['fileinfo_url']);
            exit;
        }
        else {
            print "Could not find entry or fileinfo:\n";
            print_r($url_data);
            print_r($entry);
            die("Aborting request");
        }
    } // end func login_page


    /**
     * error_page - A request response function called for an
     *              authenticated but unauthorized user.
     *
     * @access  public
     **/
    public function error_page() { $this->login_page(); }

    private function force_response( $arr=null ) {
        if ( is_null($arr) ) return $this->force_is_authorized;
        $this->force_is_authorized = $arr['authorized'];
    }

    public function is_protected( $entry_id=null ) {
        return 1;
    }


}

define( 'SUBROSA_POLICY', 'Policy_CCSAMembersAuth' );

?>