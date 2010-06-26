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

    function __construct() {
        global $mt;
        $mt->init_auth();
    }

    /**
     * check_request - Alias to is_authorized()
     *
     * @access  public
     * @param   int     $entry_id
     * @global  SubRosa $_GLOBALS['mt']
     * @return  bool
     **/
    public function check_request( $entry_id=null ) {
        global $mt;
        $mt->marker("In check_request with entry_id $entry_id, ".__FILE__);
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

        // Fetch details about the entry or entries in context
        $this->entries =& $this->resolve_entry( $entry_id );
        $entries       =& $this->entries;

        // $this->access_type() inspects $this->entries and returns
        // the strictest access policy found amongst them.  
        $e_access_type  =  $this->access_type(); 

        // Since only entries are protected, return true if none in context
        if ( count($entries) == 0 ) {
            $mt->marker('AUTHORIZED: No entry in context, access is Public');
            return true;
        }

        // We return true if null because it indicates all entries are PUBLIC
        if ( is_null($e_access_type) ) {
            $mt->marker('AUTHORIZED: Entry is not protected');
            return true;
        }

        ////////////////////////////////////////////////////////////////////
        //                          IMPORTANT                             //
        // From here on, the requested document is known to be PROTECTED  //
        ////////////////////////////////////////////////////////////////////

        // Resolve the current user.
        $user =& $mt->auth->user();

        // An unset $user indicates lack of authentication
        // User must be authenticated to view a protected
        // document so access must be denied
        if ( ! isset($user) ) {
            $mt->marker(  'NOT AUTHORIZED: Document protected and '
                        . 'user is not authenticated');
            return $this->not_authorized();
        }
        $mt->marker('User is authenticated: '. $user->get('name'));

        // Some handy shortcut variables
        $u_status      = $user->get('field.private_ccsa_member_status');
        $u_type        = $user->get('field.private_ccsa_member_type');
        $u_is_staff    = (    $user->get('field.private_ccsa_company_id')
                            == $mt->config('imisadminaccountid')       );
        $u_is_inactive = ($u_status != 'A') && ($u_status != 'CM');
                   // Active statuses are:  A (Active) and CM (Complimentary)

        // Protected documents require an active status
        if ( $u_is_inactive ) {
            $mt->marker('NOT AUTHORIZED: User not active: '.$u_status);
            return $this->not_authorized();
        }
        $mt->marker('User has an active status: '.$u_status);

        // Return true if the user is Staff since they can see anything
        if ( $u_is_staff ) {
            $mt->marker('AUTHORIZED: User is staff');
            return true;
        }
        $mt->marker('User is not staff');

        // If the document is a staff only document, return not_authorized
        // since we now know the user is NOT staff
        // FIXME ccsa_access_type doesn't have a "CCSA Staff" value!
        // plugins/CCSATheme/config.yaml shows the following:
        //          'Public,Members Only,Members Only (no vendors)'
        if ( $e_access_type == 'CCSA Staff' ) {
            $mt->marker('NOT AUTHORIZED: Document is staff only');
            return $this->not_authorized();
        }
        $mt->marker('Document is not restricted to staff');

        /*
         *  CONTENT PROGRAM RESTRICTIONS CHECK
         */
        $all_programs = array();
        foreach ($entries as $entry) {
            // ###  $e_meta =  $mt->db->get_meta( 'entry', $entry_id );
            $e_program      = $entry['entry_field.ccsa_access_program'];
            foreach (explode(',', $e_program) as $p) {
                $all_programs[$p] = 1;
            }
        }

        // Return true unless the document has content program restrictions
        if ( count(array_keys( $all_programs )) == 0 ) {
            $mt->marker(  'Document is not restricted to content group. '
                        . 'User is authorized.');
            return true;
        }
        $mt->marker( 'Content is specific to content program(s): '
                    . implode(', ', $all_programs) );

        // Only members in Content programs can see program-specific docs
        foreach ( array_keys( $all_programs ) as $program ) {
            if ($program == 'Charter Launch') $program = 'chl';
            $user_field = 'private_ccsa_member_'.strtolower($program);
            if (isset( $user[$user_field] )) {
                $mt->marker("User is authorized by content group: $program");
                return true;
            }
        }

        $mt->marker('NOT AUTHORIZED: Not content group member');
        return $this->not_authorized();
    } // end func is_authorized


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
        $mt->marker('In access_type, '.__FILE__);

        // Initialize $strictest to least strict policy: Public
        $strictest = 'Public';
        $levels    =& $this->access_level; // Shorter name

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

        // Iterate over and evaluate policies of $entries
        // Compare policy to $strictest, and set the latter
        // if found policy is stricter
        foreach ( $entries as $entry ) {
            $access = $entry['entry_field.ccsa_access_type'];            
            if ( $levels[$access] > $levels[$strictest] ) 
                $strictest = $access;
        }

        $mt->marker("Access type: $e_access_type");

        // A "Public" access policy returns null. Essentially no access ctrl
        if ( $strictest != 'Public' ) return $strictest;
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
        if ( ! isset($entry) ) {
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
            return;
        }

        // If this is not an entry archive return without an entry
        $template_type = $url_data['template']['template_type'];
        if ( isset($template_type) && ( $template_type != 'individual' ))
            return;

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



    /**
     * resolve_entries_from_asset - In-context entry(ies) from asset assocs.
     *
     * @access  public
     * @global  SubRosa     $_GLOBALS['mt']
     * @return  array|null  Array of entry object hashes
     **/
    public function &resolve_entries_from_asset() {
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
}

define( 'SUBROSA_POLICY', 'Policy_CCSAAuth' );

?>