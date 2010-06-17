<?php
require_once( 'SubRosa/PolicyAbstract.php' );

//To edit the Apache conf you will need to edit:
//
// ~root/www/conf/vhost.conf
//
// ssh root@stage.calcharters.org
//
// Send me your SSH pub key.
// 
// http://stage.calcharters.org/cgi-bin/mt/mt.cgi
// username/password: jallen/jallen
// 
// Github:
// /var/github/
// 
// Web:
// ~root/www => /var/www/vhosts/calcharters.org/httpdocs|cgi-bin
// 


/**
* Policy_CCSAAuth - SubRosa policy object which restricts blog
*                   resources to only authors on that blog.
*/
class Policy_CCSAAuth extends SubRosa_PolicyAbstract {

    var $request;
    var $is_asset_request = 0;
    var $entry;
    var $url_data;

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
        $mt->marker('In check_request, '.__FILE__);
        return $this->is_authorized( $entry_id );
    }

    public function is_protected( $entry_id=null )  {
        global $mt;
        $mt->marker('In is_protected, '.__FILE__);
        $entry =& $this->resolve_entry( $entry_id );
        if ( isset($entry) ) {
            $this->entry   = $mt->db->expand_meta($entry);
            $access        = $entry['ccsa_access_type'];
            if ( $access != 'Public' ) return $access;
        }
    }

    public function is_authorized( $entry_id=null ) {
        global $mt;
        $mt->marker('In is_authorized, '.__FILE__);

        // Fetch details about the entry in question
        $entry       =& $this->resolve_entry( $entry_id );
        $this->entry = $mt->db->expand_meta( $entry );
        // print '<pre>YO: '.print_r($entry, true)."\n------ END OF YO--------</pre>";

        // $e_meta         =  $mt->db->get_meta( 'entry', $entry_id );
        $e_program      =  $entry['ccsa_access_program'];
        $e_access_type  =  $this->is_protected( $entry_id ); // null if public

        // Fetch details about the current user
        $user           =& $mt->auth->user();  // Can be null if not auth'd'
        if ( $user ) {
            $u_status   =  $user->get('private_ccsa_member_status');
            $u_type     =  $user->get('private_ccsa_member_type');
            $u_is_staff =  (    $user->get('private_ccsa_company_id')
                             == $mt->config('iMISAdminAccountId')       );
        }

        // Execute the decision tree...
        //
        // Everyone is_authorized for public documents
        if ( is_null($e_access_type) )              return true;

        // Non-public documents require authentication
        if ( ! $user )                              return $this->not_authorized();

        // Non-public documents require active membership
        if ( $u_status != 'A=Active Member')        return $this->not_authorized();

         // Staff can see anything
         if ( $u_is_staff )                         return true;

        // Only Staff can view Staff-only documents
        if ( $e_access_type == 'CCSA Staff' )       return $this->not_authorized();

        // Only members in Content programs can see program-specific docs
        if ( $e_program ) {
            foreach ( explode(',', $e_program) as $program ) {
                if ($program == 'Charter Launch') $program = 'chl';
                $user_field = 'private_ccsa_member_'.strtolower($program);
                if (isset( $user[$user_field] ))
                    return true;
            }
        }

        // Default to unauthorized to be safe
        return $this->not_authorized();
    }

    public function not_authorized() {
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
            $url = $url_data['fileinfo']['fileinfo_url'];
            header("Location: $url");
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

    public function &resolve_entry( $entry_id=null ) {
        global $mt;
        $mt->marker('Resolving entry with ID: '.$entry_id);
        
        // If we have an entry ID, by all means load the entry and return it
        if ( ! is_null( $entry_id )) {
            $entry =& $mt->db->fetch_entry($entry_id);
            return $entry;
        }

        // Otherwise, try to discover the entry via the REQUEST_URI
        // with the fileinfo lookup that is resolve_url(). Return if found.
        $this->request = $mt->fix_request_path();
        $mt->marker('this request: '.$this->request);

        // resolve_url() gives us an array of the blog, 
        // template, templatemap and fileinfo for any URL
        $url_data =& $mt->resolve_url( $this->request );
        if ( isset( $url_data )) {
            // print '<pre>YO: '.print_r($url_data, true)."\n------ END OF YO--------</pre>";

            $this->url_data = $url_data;

            // If this is not an entry archive return without an entry
            $template_type = $url_data['template']['template_type'];
            if ( isset($template_type) and $template_type != 'entry' ) return;

            $entry_id      = $url_data['fileinfo']['fileinfo_entry_id'];
            if ( $entry_id ) {
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
            array('file_name' => basename( $this->request ))
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
    }
}

define( 'SUBROSA_POLICY', 'Policy_CCSAAuth' );

?>