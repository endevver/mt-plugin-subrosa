<?php
require_once( 'SubRosa/PolicyAbstract.php' );

/**
* Policy_CCSAAuth - SubRosa policy object which restricts blog
*                   resources to only authors on that blog.
*/
class Policy_CCSAAuth extends SubRosa_PolicyAbstract {

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

        // Fetch details about the entry or entries in context, if any
        $this->entries =& $this->resolve_entry( $entry_id );
        $entries       =& $this->entries;

        // Calculate access policies for $entries
        $e_access_type =  $this->access_type(); // See access_type() for info
        $e_flag = array(
            'no_vendors' => ( $e_access_type == 'Members Only (no vendors)' ),
            'staff_only' => ( $e_access_type == 'CCSA Staff' )
        );

        /**
         * This begins evaluation of the CCSA authorization business logic
         */
        
        // UNPROTECTED CONTENT
        // Return true if request target is not protected
        if ( $this->is_protected( $entries ) === false ) {
            $mt->marker('AUTHORIZED: Request is for a public resource');
            return true;
        }

        // USER AUTHENTICATION
        // Resolve the current user to test for authorization
        // The user must be authenticated to view protected content
        $user =& $this->resolve_user();
        if ( ! isset($user) ) {
            $mt->marker(  'NOT AUTHORIZED: User must be authenticated');
            return $this->not_authorized();
        }

        // Retrieve the assoc array of CCSA-specific data
        $u_ccsa = $user->get('ccsa'); 
        $mt->marker( 'Current user data: '.print_r($user) );
        
        // ACTIVE USER STATUS
        // Protected documents require an active status
        if ( $u_ccsa['is_active'] == false ) {
            $mt->marker('NOT AUTHORIZED: User not active: '
                        .$u_ccsa['status']);
            return $this->not_authorized();
        }

        // STAFF USERS
        // Return true if the user is Staff since they can see anything
        if ( $u_ccsa['is_staff'] == true ) {
            $mt->marker('AUTHORIZED: User is staff');
            return true;
        }

        // STAFF-ONLY DOCUMENTS
        // Since the user is not CCSA staff, deny access 
        // if the document is designated as staff only
        if ( $e_flag['staff_only'] == true ) {
            $mt->marker('NOT AUTHORIZED: Document is staff only');
            return $this->not_authorized();
        }

        // VENDOR-RESTRICTED DOCUMENTS
        // Deny access "Vendor" members access if the document's
        // access type is set to 'Members Only (no vendors)' 
        if ( $u_ccsa['is_vendor'] && $e_flag['no_vendors'] ) {
            $mt->marker('NOT AUTHORIZED: Document restricted to non-vendors');
            return $this->not_authorized();
        }

        if ( ! $this->has_cprogram_access( $user, $entries ) ) {
            $mt->marker('NOT AUTHORIZED: Not content group member');
            return $this->not_authorized();
        }

        public function has_cprogram_access( $user, $entries ) {
            
            // CONTENT PROGRAMS
            $cprograms        = $this->cprograms_for_entry( $entries );
            $has_content_program = count( $cprograms );

            // Only Content programs members can see cprogram-specific docs
            if ( $has_content_program ) {
                $mt->marker( 'Content is specific to content program(s): '
                    . implode(', ', $cprograms) );

                // Iterate through each content program found to test whether
                // the user is a member of the group.  As long as one content
                // program matches, the user is authorized
                foreach ( $cprograms as $program ) {
                    // Charter Launch is non-standard value in that it doesn't
                    // mesh with its user field. So we modify it.
                    if ($program == 'Charter Launch') $program = 'chl';
                    $user_field = 'private_ccsa_member_'.strtolower($program);
                    if (isset( $user[$user_field] )) {
                        $mt->marker("User is authorized by content group: $program");
                        return true;
                    }
                }
                $mt->marker(  'Document is not restricted to content group. '
                            . 'User is authorized.');
                return true;
            }

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
     * access_type - Returns the access type for one or more entries
     *
     * The access type of an entry is a custom field that defines the minimum
     * member status necessary to view it or the document assets it contains. 
     * When called with no arguments, the method inspects $this->entries, an
     * array containing zero to many entries in context for the current
     * request.  If more than one entry exists, the most strict access type 
     * of the entries is returned.
     *
     * @access  public
     * @param   int     $entry_id
     * @global  SubRosa $_GLOBALS['mt']
     * @return  string|null
     **/
    public function access_type( $entry_id=null )  {
        global $mt;

        if ( is_null( $entry_id ) && isset( $this->request_access_type )) {
            return $this->request_access_type;
        }

        // Initialize $strictest to least strict policy: Public
        $strictest = 'Public';
        $levels    =& $this->access_level; // Shorter name
        $mt->marker('Access levels: '.print_r( $levels, true ));

        // Populate array of $entries to evaluate
        // If $entry_id is provided, only check that entry
        if ( ! is_null( $entry_id )) {
            $entry =& $mt->db->fetch_entry($entry_id);
            $entries = array( $entry );
        }
        // Otherwise, check policies of all entries in $this->entries,
        // returning the strictest policy found.  This becomes essentially
        // the access policy for the request.
        else {
            $entries = $this->entries;
        }

        // Iterate over and evaluate policies of $entries, if any.
        // Compare policy to $strictest, and set the latter
        // if found policy is stricter
        foreach ( $entries as $entry ) {
            $access = $entry['entry_field.ccsa_access_type'];            
            if ( $levels[$access] > $levels[$strictest] ) 
                $strictest = $access;
        }
        $mt->marker("Access type: $strictest");

        // Switch a "Public" access policy value to null, i.e. no restriction
        if ( $strictest == 'Public' ) $strictest == null;

        // For inquiries not specific to a single $entry_id, cache the
        // $strictest to be used as a request-level access type
        if ( ! $entry_id ) $this->request_access_type = $strictest;

        // If we end up with a null value, return a NULL value
        if ( is_null( $strictest )) return;

        // Otherwise, return a array with extra information for access tests
        return array(
            'label'      => $strictest,
            'no_vendors' => ( $strictest == 'Members Only (no vendors)' ),
            'staff_only' => ( $strictest == 'CCSA Staff' )
        )
    } // end func access_type


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
            return false;
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

    /**
     * resolve_entry - Identify and load in-context entries
     *
     * @access  public
     * @param   int     $entry_id
     * @global  SubRosa $_GLOBALS['mt'] 
     * @return  array   Array of entry object hashes
     **/
    public function cprograms_for_entry() {
        // FIXME Clean up cprograms_for_entry argument handling and ret val
        if ( $fnargs = func_get_args() ) {

            if (   is_object($fnargs[0]) 
                || SubRosa_Util::is_assoc_array( $fnargs[0])) {
                $entries = array( $fnargs[0] ); // Single entry
            }
            elseif ( is_array($fnargs[0]) ) {
                $entries = $fnargs[0];          // Array of entries
            }
        }
        else {
            return;   // No entry or entries array provided
        }

        // Extract the content programs from the entries
        $all_programs = array();
        foreach ($entries as $entry) {
            $e_program      = $entry['entry_field.ccsa_access_program'];
            foreach (explode(',', $e_program) as $p) {
                $all_programs[$p] = 1;
            }
        }
        return array_keys($all_programs);
    }

    /**
     * resolve_entry - Identify and load in-context entries
     *
     * @access  public
     * @param   int     $entry_id
     * @global  SubRosa $_GLOBALS['mt'] 
     * @return  array   Array of entry object hashes
     **/
    public function &resolve_entry( $entry_id=null ) {
        global $mt;
        $mt->marker('Resolving entry with ID: '.$entry_id);

        // Load entry from DB with $entry_id if given
        if ( ! is_null( $entry_id ))
            $entry =& $mt->db->fetch_entry($entry_id);

        // Try to resolve entry via fileinfo lookup of REQUEST_URI
        if ( !isset($entry) )
            $entry =& $this->resolve_entry_from_fileinfo();

        // Assume that current request is for an asset
        // Try to resolve the entry or entries from the asset association(s)
        $skip = (isset($entry) || $this->force_response());
        if ( ! $skip ) {
            $entries =& $this->resolve_entries_from_asset();
            $this->is_asset_request = isset($entries);
        }

        // Force conversion of $entry to single-element array, $entries
        if ( isset($entry) ) $entries = array( $entry );

        $mt->marker(  'Entry or entries resolved: '
                    . ( isset($entries) ? print_r($entries, true) : 'NONE') );

        return $entries;
    } // end func resolve_entry


    /**
     * resolve_entry_from_fileinfo - In-context entry from fileinfo record
     *
     * @access  public
     * @global  SubRosa $_GLOBALS['mt']
     * @return  array|null  Array is an entry object hash
     **/
    public function &resolve_entry_from_fileinfo() {
        global $mt;
        $this->request = $mt->fix_request_path();
        $mt->marker('this request: '.$this->request);

        // resolve_url() gives us an array of the blog, 
        // template, templatemap and fileinfo for any URL
        $url_data =& $mt->resolve_url( $this->request );
        if ( ! isset($url_data) ) return;

        // If we get back fileinfo data, an entry is definitely in context
        $mt->marker('URL data for entry: '.print_r($url_data, true));

        $this->url_data = $url_data;

        // Page-class entries are never protected
        if ($url_data['fileinfo']['fileinfo_archive_type'] == 'Page') {
            return $this->force_response( array('authorized' => true ) );
        }

        // If this is not an entry archive return without an entry
        $template_type = $url_data['template']['template_type'];
        if ( isset($template_type) && ( $template_type != 'individual' )) {
            return $this->force_response( array('authorized' => true ) );
        }

        // If the fileinfo gives us an entry ID, load and return it
        $entry_id      = $url_data['fileinfo']['fileinfo_entry_id'];
        if ( isset($entry_id) ) {
            $mt->marker("Found entry ID: $entry_id");
            $entry =& $mt->db->fetch_entry( $entry_id );
            if ( isset($entry) ) {
                $mt->marker('Found entry ID '.$entry['entry_id']);
                return $entry;
            }
        }

        // We should never get here.  If we do, we have a fileinfo record
        // that does not correspond to an existing entry.  
        // Raise hell...
        $mt->marker(  'Fileinfo returned but missing entry: '
                    . print_r($url_data, true));
    } // end func resolve_entry_from_fileinfo

    private function force_response( $arr=null ) {
        if ( is_null($arr) ) return $this->force_is_authorized;
        $this->force_is_authorized = $arr['authorized'];
    }

    /**
     * resolve_entries_from_asset - In-context entry(ies) from asset assocs.
     *
     * @access  public
     * @global  SubRosa     $_GLOBALS['mt']
     * @return  array|null  Array of entry object hashes
     **/
    private function &resolve_entries_from_asset() {
        global $mt;

        // Load all assets with the same filename, return if none
        require_once('SubRosa/MT/Object/Asset.php');
        $assets = SubRosa_MT_Object_Asset::load(
            array('file_name' => basename( urldecode($this->request) ))
        );
        if ( ! isset( $assets )) return;

        $mt->marker('Assets loaded: '.print_r($assets, true));

        // Go through returned objects trying to match the REQUEST_URI
        // to the asset URL. Necessary to avoid matching twice, once
        // normally and once with %r in place of the blog URL.
        if ( is_object($assets) ) {
            $asset = $assets; 
        }
        else {
            // FIXME: We need to also look for the %r/%s/%a variant
            $pattern = "/${$this->request}$/";
            foreach ( $assets as $a ) {
                if (preg_match( $pattern, $a->url )) {
                    $asset = $a;
                    break;
                }
            }
            if ( ! isset($asset) ) {
                $mt->marker(
                    sprintf('Assets found matching basename %s, but '
                            .'none found with matching request URI, %s',
                            basename( urldecode($this->request) ),
                            $this->request
                    )
                );
                return;
            }
        }

        // Load all ObjectAsset records for $asset. 
        require_once('SubRosa/MT/Object/ObjectAsset.php');
        $oaterms = array(
            'object_ds' => 'entry',
            'blog_id'   => $asset->get('blog_id'),
            'asset_id'  => $asset->get('id')
        );
        $mt->marker('ObjectAsset load terms: '.print_r($oaterms, true));

        $oassets = SubRosa_MT_Object_ObjectAsset::load( $oaterms );
        if ( ! isset( $oassets )) {
            $mt->marker('No object assets found for asset!');
            return;
        }

        // If only one returned, force conversion to single-element array
        if ( is_object( $oassets )) $oassets = array( $oassets );
        $mt->marker('OAssets found: '.print_r($oassets, true));

        // Load entry corresponding to each object asset in order to
        // determine protection status.  In the case of multiple entries
        // with different levels of protection, use the strictest.
        foreach ( $oassets as $oasset ) {
            $entry =& $mt->db->fetch_entry( $oasset->get('object_id') );
            if ( isset($entry) ) $entries[] = $entry;
        }

        return $entries;
    } // end func resolve_entries_from_asset


    /**
     * is_protected - In-context entry(ies) from asset assocs.
     *
     * @access  public
     * @return  bool    True if not Public access type
     **/
    public function is_protected( $entries=array() ) {
        global $mt;
        
        if ( ! $entries ) $entries
        // Since only entries are protected, return true if none in context
        if ( count($entries) == 0 ) {
            $mt->marker('No entry in context, document is not protected');
            return false;
        }

        // Now, check to see whether the current request has a non-public
        // access_type. $this->access_type() inspects $this->entries and
        // returns the strictest access policy found amongst them.          
        // If the return value is NULL, it means that none of the entries are
        // protected so we return false
        $access_policy = $this->access_type();
        if ( ! $access_policy ) {
            $mt->marker('No entry in context has a protected access type.');
            return false;
        }

        // Otherwise, the request is for a protected asset
        return true;
    }
}

define( 'SUBROSA_POLICY', 'Policy_CCSAAuth' );

?>