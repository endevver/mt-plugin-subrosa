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
        $entry  =& $this->resolve_entry( $entry_id );
        if ( isset($entry) ) {
            $entry  = $mt->db->expand_meta($entry);
            $access = $entry['ccsa_access_type'];
            if ( $access != 'Public' ) return $access;
        }
    }

    public function is_authorized( $entry_id=null ) {
        global $mt;
        $mt->marker('In is_authorized, '.__FILE__);

        // Fetch details about the entry in question
        $entry =& $this->resolve_entry( $entry_id );
        $entry = $mt->db->expand_meta($entry);
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
        if ( ! $user )                              return not_authorized();

        // Non-public documents require active membership
        if ( $u_status != 'A=Active Member')        return not_authorized();

         // Staff can see anything
         if ( $u_is_staff )                         return true;

        // Only Staff can view Staff-only documents
        if ( $e_access_type == 'CCSA Staff' )       return not_authorized();

        // FIXME -- What are the special considerations for vendors?
        // if ( $u_type == 'Vendor')

        // FIXME -- Not sure how to implement Content Program-specific check:
        //          How do I know whether a user is in a program?
        //          If $e_program (a multi-checkbox) a comma-delimited value?
        // What user metadata field(s) define(s) whether a person is part of
        // any particular "content program"?
        //
        //     field.private_ccsa_member_zoom
        //     field.private_ccsa_member_chl
        //     field.private_ccsa_member_jpa
        //     field.private_ccsa_member_ces
        //
        // Those are boolean values.
        // 
        // They correspond to an entry level custom field called "Restrict to Program". If an entry is restricted to Zoom, then their author custom field "private_ccsa_member_zoom" must also be true for them to see the content.
        // 
        // The entry level custom field will be a comma delimited list of programs the content is restricted to.
        //
        // Only members in Content programs can see program-specific docs
        if ( $e_program ) {
            return not_authorized(); // Returning false until 
                                     // this is implemented
           /*
           ccsa_access_program:
             name: Restrict to Program
             description: 'Restricts access to members of the selected programs. This assumes the "Access Control" field is set to "Members Only."'
             type: multi_checkbox
             options: 'Zoom,CES,Charter Launch,JPA'
             tag: EntryRestrictToProgram
             obj_type: entry
           */
        }

        // Default to unauthorized to be safe
        return not_authorized();
    }

    public function not_authorized() {
        $user =& $mt->auth->user();  // Can be null if not auth'd'
        if ( $this->is_asset_request ) {
            return isset($user) ? error_page() : login_page();
        }
        else {
            return false;
        }
    }

    public function login_page() { }
    public function error_page() { }

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