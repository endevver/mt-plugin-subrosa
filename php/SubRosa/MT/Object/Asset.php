<?php
require_once 'MT/Object.php';
/**
* MT_Asset - Asset object for dynamic MT
*/
class MT_Object_Asset extends MT_Object
{
    var $class_prefix = 'asset';
    var $properties = array(
        'id', 'blog_id', 'label', 'url', 'description', 'file_path',
        'file_name', 'file_ext', 'mime_type', 'parent'
    );

    function load() {
        if ($fnargs = func_get_args()) {
            if (is_array($fnargs[0])) {
                $terms = $fnargs[0];
            }
            elseif (is_string($fnargs[0])) {
                $terms = array( id => $fnargs[0]);
            }
        }
        global $mt;
        if ($results = $mt->db->load('asset', $terms)) {
            foreach ($results as $data) {
                $object = new MT_Asset($data);
                $objects[] = $object;
            }
            return (count($objects) == 1) ? $objects[0] : $objects;
        }
    }

    function load_by_id($id) {
        $terms = array( id => $id, type => 1);
        if (list($obj) = $this->load($terms)) {
            // $user = parent::init($userdata);
            return $obj;
        }
    }


//
//              Ballotelli/SST    Ichikawa/RST
// Gayle/DMR        Fabregas/MC    Whitzelder/DMR  Higuain/AMR
// Delderfield  Wolfe       Chapman     Lahm
//
//
//
//


?>