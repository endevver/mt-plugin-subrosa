<?php
require_once 'MTObject.php';
/**
* MTObjectAsset - ObjectAsset object for dynamic MT
*/
class MTObjectAsset extends MTObject
{
    var $class_prefix = 'objectasset';
    var $properties = array(
        'id', 'blog_id', 'object_id', 'object_ds', 'asset_id', 'embedded'
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
        if ($results = $mt->db->load('objectasset', $terms)) {
            foreach ($results as $data) {
                $object = new MTObjectAsset($data);
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

?>