<?php
# Movable Type (r) (C) 2001-2010 Six Apart, Ltd. All Rights Reserved.
# This code cannot be redistributed without permission from www.sixapart.com.
# For more information, consult your Movable Type license.
#
# $Id: MTViewer.php 3455 2009-02-23 02:29:31Z auno $

include_once("Smarty.class.php");
require_once('MTViewer.php');
class SubRosa_MT_Viewer extends MTViewer {

    function add_tag($tag, $fn) {
        global $mt;
        $mt->marker('In SubRosa_MT_Viewer::add_tag with '.$tag);

        // Check for a trailing question mark and get rid of it
        $tag = preg_replace( '/\?$/', '', $tag, -1, $has_question_mark );
        if ( $has_question_mark ) 
          $mt->marker($tag.': Stripped question mark. Processing as block tag.');

        // Tags with trailing question marks should be block tags instead
        return $this->register_tag_handler(
            $tag, $fn, ( $has_question_mark ? 'block' : 'function' )
        );
    }
}

?>