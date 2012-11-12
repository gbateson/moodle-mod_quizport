<?php
/**
 * Library functions for using AJAX with Moodle.
 */

/**
 * Get the path to a JavaScript library.
 * @param $libname - the name of the library whose path we need.
 * @return string
 */
function ajax_get_lib($libname) {

    global $CFG, $HTTPSPAGEREQUIRED;
    $libpath = '';

    $translatelist = array(
            'yui_yahoo' => '/yui/yahoo/yahoo-min.js',
            'yui_animation' => '/yui/animation/animation-min.js',
            'yui_autocomplete' => '/yui/autocomplete/autocomplete-min.js',
            'yui_button' => '/yui/button/button-min.js',
            'yui_calendar' => '/yui/calendar/calendar-min.js',
            'yui_charts' => '/yui/charts/charts-experimental-min.js',
            'yui_colorpicker' => '/yui/colorpicker/colorpicker-min.js',
            'yui_connection' => '/yui/connection/connection-min.js',
            'yui_container' => '/yui/container/container-min.js',
            'yui_cookie' => '/yui/cookie/cookie-min.js',
            'yui_datasource' => '/yui/datasource/datasource-min.js',
            'yui_datatable' => '/yui/datatable/datatable-min.js',
            'yui_dom' => '/yui/dom/dom-min.js',
            'yui_dom-event' => '/yui/yahoo-dom-event/yahoo-dom-event.js',
            'yui_dragdrop' => '/yui/dragdrop/dragdrop-min.js',
            'yui_editor' => '/yui/editor/editor-min.js',
            'yui_element' => '/yui/element/element-beta-min.js',
            'yui_event' => '/yui/event/event-min.js',
            'yui_get' => '/yui/get/get-min.js',
            'yui_history' => '/yui/history/history-min.js',
            'yui_imagecropper' => '/yui/imagecropper/imagecropper-beta-min.js',
            'yui_imageloader' => '/yui/imageloader/imageloader-min.js',
            'yui_json' => '/yui/json/json-min.js',
            'yui_layout' => '/yui/layout/layout-min.js',
            'yui_logger' => '/yui/logger/logger-min.js',
            'yui_menu' => '/yui/menu/menu-min.js',
            'yui_profiler' => '/yui/profiler/profiler-min.js',
            'yui_profilerviewer' => '/yui/profilerviewer/profilerviewer-beta-min.js',
            'yui_resize' => '/yui/resize/resize-min.js',
            'yui_selector' => '/yui/selector/selector-beta-min.js',
            'yui_simpleeditor' => '/yui/editor/simpleeditor-min.js',
            'yui_slider' => '/yui/slider/slider-min.js',
            'yui_tabview' => '/yui/tabview/tabview-min.js',
            'yui_treeview' => '/yui/treeview/treeview-min.js',
            'yui_uploader' => '/yui/uploader/uploader-experimental-min.js',
            'yui_utilities' => '/yui/utilities/utilities.js',
            'yui_yuiloader' => '/yui/yuiloader/yuiloader-min.js',
            'yui_yuitest' => '/yui/yuitest/yuitest-min.js',
            'ajaxcourse_blocks' => '/ajax/block_classes.js',
            'ajaxcourse_sections' => '/ajax/section_classes.js',
            'ajaxcourse' => '/ajax/ajaxcourse.js'
            );

    if (!empty($HTTPSPAGEREQUIRED)) {
        $wwwroot = $CFG->httpswwwroot;
    } else {
        $wwwroot = $CFG->wwwroot;
    }

    if (array_key_exists($libname, $translatelist)) {
        $testpath = $CFG->legacylibdir . $translatelist[$libname];
        $libpath = str_replace($CFG->dirroot, $wwwroot, $testpath);
    } else {
        $libpath = $libname;
        $testpath = str_replace($wwwroot, $CFG->dirroot, $libpath);
    }

    if (!file_exists($testpath)) {
        error('require_js: '.$libpath.' - file not found.');
    }

    return $libpath;
}


/**
 * Returns whether ajax is enabled/allowed or not.
 */
function ajaxenabled($browsers = array()) {

    global $CFG, $USER;

    if (!empty($browsers)) {
        $valid = false;
        foreach ($browsers as $brand => $version) {
            if (check_browser_version($brand, $version)) {
                $valid = true;
            }
        }

        if (!$valid) {
            return false;
        }
    }

    $ie = check_browser_version('MSIE', 6.0);
    $ff = check_browser_version('Gecko', 20051106);
    $op = check_browser_version('Opera', 9.0);
    $sa = check_browser_version('Safari', 412);

    if (!$ie && !$ff && !$op && !$sa) {
        /** @see http://en.wikipedia.org/wiki/User_agent */
        // Gecko build 20051107 is what is in Firefox 1.5.
        // We still have issues with AJAX in other browsers.
        return false;
    }

    if (!empty($CFG->enableajax) && (!empty($USER->ajax) || !isloggedin())) {
        return true;
    } else {
        return false;
    }
}


/**
 * Used to create view of document to be passed to JavaScript on pageload.
 * We use this class to pass data from PHP to JavaScript.
 */
class jsportal {

    var $currentblocksection = null;
    var $blocks = array();


    /**
     * Takes id of block and adds it
     */
    function block_add($id, $hidden=false){
        $hidden_binary = 0;

        if ($hidden) {
            $hidden_binary = 1;
        }
        $this->blocks[count($this->blocks)] = array($this->currentblocksection, $id, $hidden_binary);
    }


    /**
     * Prints the JavaScript code needed to set up AJAX for the course.
     */
    function print_javascript($courseid, $return=false) {
        global $CFG, $USER;

        $blocksoutput = $output = '';
        for ($i=0; $i<count($this->blocks); $i++) {
            $blocksoutput .= "['".$this->blocks[$i][0]."',
                             '".$this->blocks[$i][1]."',
                             '".$this->blocks[$i][2]."']";

            if ($i != (count($this->blocks) - 1)) {
                $blocksoutput .= ',';
            }
        }
        $output .= "<script type=\"text/javascript\">\n";
        $output .= "    main.portal.id = ".$courseid.";\n";
        $output .= "    main.portal.blocks = new Array(".$blocksoutput.");\n";
        $output .= "    main.portal.strings['wwwroot']='".$CFG->wwwroot."';\n";
        $output .= "    main.portal.strings['pixpath']='".$CFG->pixpath."';\n";
        $output .= "    main.portal.strings['marker']='".get_string('markthistopic', '', '_var_')."';\n";
        $output .= "    main.portal.strings['marked']='".get_string('markedthistopic', '', '_var_')."';\n";
        $output .= "    main.portal.strings['hide']='".get_string('hide')."';\n";
        $output .= "    main.portal.strings['hidesection']='".get_string('hidesection', '', '_var_')."';\n";
        $output .= "    main.portal.strings['show']='".get_string('show')."';\n";
        $output .= "    main.portal.strings['delete']='".get_string('delete')."';\n";
        $output .= "    main.portal.strings['move']='".get_string('move')."';\n";
        $output .= "    main.portal.strings['movesection']='".get_string('movesection', '', '_var_')."';\n";
        $output .= "    main.portal.strings['moveleft']='".get_string('moveleft')."';\n";
        $output .= "    main.portal.strings['moveright']='".get_string('moveright')."';\n";
        $output .= "    main.portal.strings['update']='".get_string('update')."';\n";
        $output .= "    main.portal.strings['groupsnone']='".get_string('groupsnone')."';\n";
        $output .= "    main.portal.strings['groupsseparate']='".get_string('groupsseparate')."';\n";
        $output .= "    main.portal.strings['groupsvisible']='".get_string('groupsvisible')."';\n";
        $output .= "    main.portal.strings['clicktochange']='".get_string('clicktochange')."';\n";
        $output .= "    main.portal.strings['deletecheck']='".get_string('deletecheck','','_var_')."';\n";
        $output .= "    main.portal.strings['resource']='".get_string('resource')."';\n";
        $output .= "    main.portal.strings['activity']='".get_string('activity')."';\n";
        $output .= "    main.portal.strings['sesskey']='".$USER->sesskey."';\n";
        $output .= "    onloadobj.load();\n";
        $output .= "    main.process_blocks();\n";
        $output .= "</script>";
        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }

}

?>
