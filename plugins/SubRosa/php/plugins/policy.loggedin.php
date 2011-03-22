<?php
require_once( 'SubRosa/PolicyAbstract.php' );

class Policy_LoggedIn extends SubRosa_PolicyAbstract {

    var $request;
    var $is_asset_request = 0;
    var $entry;
    var $url_data;
    var $cached_entries = array();

    /**
     * is_protected() - This function check
     *
     * @param string $uri
     * @return boolean
     **/
    function __construct() {
        global $mt;
        $mt->init_auth();
    }

    public function check_request() {
        global $mt;
        return $this->is_authorized();
    }

    public function is_protected()  {
        /*  Is a page protected? Returning true means the page is protected, 
            returning false means the page is not protected. In general, 
            you're probably using SubRosa because you want pages to be 
            protected, so simply returning true is enough.
            
            With this function, page-level protection is possible. Based on 
            an Entry or Page property (custom field, category, tag, etc), 
            the determination could be made whether or not the currently-
            viewed page should be protected.
        */
        return true;
    }

    public function is_authorized() {
        /*  Is the current user authorized to view this page? This can be 
            used as a basic on/off switch: logged-in users can view the page
            while users who are not logged in can not view the page:
            
            // Fetch details about the current user
            $user =& $mt->auth->user();  // Can be null if not auth'd

            // If no user was found (no user logged in) return false.
            if ( ! $user ) return false;
            
            More comprehensive authorization can be managed here, too.
            Check the user's custom fields data or another property to
            determine access privileges.
        */
        global $mt;
        $mt->marker('In is_authorized, '.__FILE__);

        // Fetch details about the current user
        $user =& $mt->auth->user();  // Can be null if not auth'd
        
        /* If the user was found, they are authorized and authenticated. If
           the user was not found, return false, causing the published page to
           fall back to the $is_authenticated test or the last else.
        */
        return isset($user) ? true : false;
        
        /* Alternatively, you may want to redirect the user to a login page if
           they are not authorized or authenticated. Here we can make use of
           the login_page() function provided below.
           
           If you do choose to use this method, be sure to comment the above
           return out, and uncomment the below return statement.
        */
        //return isset($user) ? true : login_page();
    }

    public function login_page() {
        $url_data =& $this->url_data;
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
    }

}

define( 'SUBROSA_POLICY', 'Policy_LoggedIn' );

?>