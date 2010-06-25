<?php
require_once( 'SubRosa/PolicyAbstract.php' );

/**
* Policy_CCSAAuth - SubRosa policy object which restricts blog
*                   resources to only authors on that blog.
*/
class Policy_CCSAAuth extends SubRosa_PolicyAbstract {

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

    public function check_request( $entry_id=null ) {
        global $mt;
        $mt->marker("In check_request with entry_id $entry_id, ".__FILE__);
        return $this->is_authorized( $entry_id );
    }

    public function is_protected( $entry_id=null )  {
        global $mt;
        $mt->marker('In is_protected, '.__FILE__);
        $entry =& $this->resolve_entry( $entry_id );
        if ( isset($entry) ) {
            $access        = $entry['entry_field.ccsa_access_type'];
            if ( $access != 'Public' ) return $access;
        }
    }

    public function is_authorized( $entry_id=null ) {
        global $mt;
        $mt->marker('In is_authorized, '.__FILE__);

        // Fetch details about the entry in question
        $entry       =& $this->resolve_entry( $entry_id );

        if ( isset( $entry ) ) {
            $mt->log('Entry resolved: '.print_r($entry, true));
            // $e_meta         =  $mt->db->get_meta( 'entry', $entry_id );
            $e_program      =  $entry['entry_field.ccsa_access_program'];
            $e_access_type  =  $this->is_protected( $entry['entry_id'] ); // null if public
        }

        // Fetch details about the current user
        $user           =& $mt->auth->user();  // Can be null if not auth'd'
        if ( $user ) {
            $u_status   =  $user->get('field.private_ccsa_member_status');
            $u_type     =  $user->get('field.private_ccsa_member_type');
            $u_is_staff =  (    $user->get('field.private_ccsa_company_id')
                             == $mt->config('imisadminaccountid')       );
        }

        $mt->marker("Access type: $e_access_type");

        // Execute the decision tree...
        //
        // Everyone is_authorized for public documents
        if ( is_null($e_access_type) ) return true;

        $mt->marker('Not public');

        // Non-public documents require authentication
        if ( ! $user ) return $this->not_authorized();

        $mt->marker('Have authenticated user with $u_status: '.$u_status);

        // Non-public documents require (A)ctive or (C)o(M)plimentary
        if ( ($u_status != 'A') && ($u_status != 'CM') ) {
            return $this->not_authorized();
        }

        $mt->marker('User is active');

         // Staff can see anything
         if ( $u_is_staff ) return true;

        $mt->marker('User is not staff');

        // Only Staff can view Staff-only documents
        if ( $e_access_type == 'CCSA Staff' ) return $this->not_authorized();

        $mt->marker('Document is not staff only');

        // Content does NOT require special program access, so let the user
        // read the document.
        if ( ! $e_program ) return true;

        // Only members in Content programs can see program-specific docs
        // Addendum: Complementary members can also see this content
        // Correction: Error in understanding of requirements, CM users do not get
        //             any additional privs. Simply a synonym to "Active"
        foreach ( explode(',', $e_program) as $program ) {
            if ($program == 'Charter Launch') $program = 'chl';
            $user_field = 'private_ccsa_member_'.strtolower($program);
            if (isset( $user[$user_field] ))
                return true;
        }

        // Return not authorized just to be safe
        return $this->not_authorized();
    }

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
    public function error_page() { $this->login_page(); }

    public function &entry_cache($entry_id=null, $entry=null ) {
        global $mt;
        $cache_key = isset($entry_id) ? $entry_id : 'default'; //  Null entry IDs get default key
        $cache =& $this->cached_entries;
        if ( isset($entry) ) {
            $entry = $mt->db->expand_meta( $entry );
            $this->entry =& $entry;
            $cache[$entry['entry_id']] =& $entry;
            if (! isset($entry_id)) $cache['default'] =& $entry;
        }
        return $cache[$cache_key];
    }

    public function &resolve_entry( $entry_id=null ) {
        global $mt;
        $mt->marker('Resolving entry with ID: '.$entry_id);

        // Check cache first.
        $entry = $this->entry_cache( $entry_id );
        if ( isset( $entry ) ) return $entry;

        // If we have an entry ID, by all means load the entry and return it
        if ( ! is_null( $entry_id )) {
            $entry =& $mt->db->fetch_entry($entry_id);
            return $this->entry_cache( $entry_id, $entry );
        }

        // Otherwise, try to discover the entry via the REQUEST_URI
        // with the fileinfo lookup that is resolve_url(). Return if found.
        $this->request = $mt->fix_request_path();
        $mt->marker('this request: '.$this->request);

        // resolve_url() gives us an array of the blog, 
        // template, templatemap and fileinfo for any URL
        $url_data =& $mt->resolve_url( $this->request );

        if ( isset( $url_data )) {
          //$mt->log('Got URL data for entry'); //.print_r($url_data, true));
            $mt->log('Got URL data for entry: '.print_r($url_data, true));

            $this->url_data = $url_data;

            // No pages are protected
            if ($url_data['fileinfo']['fileinfo_archive_type'] == 'Page') {
                return null;
            }

            // If this is not an entry archive return without an entry
            $template_type = $url_data['template']['template_type'];
            if ( isset($template_type) and $template_type != 'individual' ) return null;

            $entry_id      = $url_data['fileinfo']['fileinfo_entry_id'];
            if ( isset($entry_id) ) {
                $mt->marker("Found entry ID: $entry_id");
                $entry  =& $this->resolve_entry( $entry_id );
                $mt->marker('Found entry? '.$entry['entry_id']);
                if ( isset($entry) ) return $entry;
            }
        }

        // We have a direct request for an asset
        $this->is_asset_request = 1;

        // Load all assets with the same filename
        require_once('SubRosa/MT/Object/Asset.php');
        $assets = SubRosa_MT_Object_Asset::load(
            array('file_name' => basename( urldecode($this->request) ))
        );

        // Go through returned objects trying to match the REQUEST_URI
        // to the asset URL. Necessary to avoid matching twice, once
        // normally and once with %r in place of the blog URL.
        $pattern = "/${$this->request}$/";
        foreach ( $assets as $a ) {
            if (preg_match( $pattern, $a->url )) {
                $asset = $a;
                break;
            }
        }
        if ( ! isset( $asset )) return;

        require_once('SubRosa/MT/Object/ObjectAsset.php');
        $oasset = SubRosa_MT_Object_Asset::load(
            array(
                'object_ds' => 'entry',
                'blog_id'   => $asset->blog_id,
                'asset_id'  => $asset->id
            )
        );

        if ( isset( $oasset )) {
            $entry  =& $this->resolve_entry( $oasset->object_id );
            return $entry;
        }
        else {
            $this->is_asset_request = 0;
        }
    }
}

define( 'SUBROSA_POLICY', 'Policy_CCSAAuth' );

?>