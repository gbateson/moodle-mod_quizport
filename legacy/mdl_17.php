<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.7
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

if (! function_exists('get_course_section')) {
    // copy of "get_course_section()" (course/lib.php)
    function get_course_section($section, $courseid) {
        global $DB;
        if ($cw = $DB->get_record('course_sections', array('section' => $section, 'course' => $courseid))) {
            return $cw;
        }
        $cw = (object)array(
            'course' => $courseid, 'section' => $section, 'summary' => '', 'sequence' => '', visible=>1
        );
        if (! $cw->id = $DB->insert_record('course_sections', $cw)) {
            error('Could not insert new course sections record');
        }
        return $cw;
    }
}

if (! function_exists('right_to_left')) {
    // copy of "right_to_left()" (lib/weblib.php)
    function right_to_left() {
        static $result;
        if (! isset($result)) {
            if (get_string('thisdirection')=='rtl') {
                $result = true;
            } else {
                $result = false;
            }
        }
        return $result;
    }
}

if (! function_exists('editorhelpbutton')) {
    // copy of "editorhelpbutton()" (lib/weblib.php)
    function editorhelpbutton(){
        global $CFG, $SESSION;
        $items = func_get_args();
        $i = 1;
        $urlparams = array();
        $titles = array();
        foreach ($items as $item){
            if (is_array($item)){
                $urlparams[] = "keyword$i=".urlencode($item[0]);
                $urlparams[] = "title$i=".urlencode($item[1]);
                if (isset($item[2])){
                    $urlparams[] = "module$i=".urlencode($item[2]);
                }
                $titles[] = trim($item[1], ". \t");
            }elseif (is_string($item)){
                $urlparams[] = "button$i=".urlencode($item);
                switch ($item){
                    case 'reading' :
                        $titles[] = get_string("helpreading");
                        break;
                    case 'writing' :
                        $titles[] = get_string("helpwriting");
                        break;
                    case 'questions' :
                        $titles[] = get_string("helpquestions");
                        break;
                    case 'emoticons' :
                        $titles[] = get_string("helpemoticons");
                        break;
                    case 'richtext' :
                        $titles[] = get_string('helprichtext');
                        break;
                    case 'text' :
                        $titles[] = get_string('helptext');
                        break;
                    default :
                        error('Unknown help topic '.$item);
                }
            }
            $i++;
        }
        if (count($titles)>1){
            //join last two items with an 'and'
            $a = new object();
            $a->one = $titles[count($titles) - 2];
            $a->two = $titles[count($titles) - 1];
            $titles[count($titles) - 2] = get_string('and', '', $a);
            unset($titles[count($titles) - 1]);
        }
        $alttag = join (', ', $titles);

        $paramstring = join('&', $urlparams);
        $linkobject = '<img alt="'.$alttag.'" class="iconhelp" src="'.$CFG->pixpath .'/help.gif" />';
        if ($CFG->majorrelease<=1.3) {
            $link_to_popup_window = 'quizport_link_to_popup_window';
        } else {
            $link_to_popup_window = 'link_to_popup_window';
        }
        return $link_to_popup_window(s('/mod/quizport/legacy/lib/form/editorhelp.php?'.$paramstring), 'popup', $linkobject, 400, 500, $alttag, 'none', true);
    }
}

if (! function_exists('print_box')) {
    // copy of "print_box()" (lib/weblib.php)
    function print_box($message, $classes='generalbox', $ids='', $return=false) {

        $output  = print_box_start($classes, $ids, true);
        $output .= stripslashes_safe($message);
        $output .= print_box_end(true);

        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }
}

if (! function_exists('print_box_start')) {
    // copy of "print_box_start()" (lib/weblib.php)
    function print_box_start($classes='generalbox', $ids='', $return=false) {
        global $THEME;

        if (strpos($classes, 'clearfix') !== false) {
            $clearfix = true;
            $classes = trim(str_replace('clearfix', '', $classes));
        } else {
            $clearfix = false;
        }

        if (!empty($THEME->customcorners)) {
            $classes .= ' ccbox box';
        } else {
            $classes .= ' box';
        }

        return print_container_start($clearfix, $classes, $ids, $return);
    }
}

if (! function_exists('print_box_end')) {
    // copy of "print_box_end()" (lib/weblib.php)
    function print_box_end($return=false) {
        return print_container_end($return);
    }
}

if (! function_exists('print_container_start')) {
    // copy of "print_container_start()" (lib/weblib.php)
    function print_container_start($clearfix=false, $classes='', $idbase='', $return=false) {
        global $THEME;

        if (!isset($THEME->open_containers)) {
            $THEME->open_containers = array();
        }
        $THEME->open_containers[] = $idbase;

        if ($clearfix) {
            $clearfix = ' clearfix';
        } else {
            $clearfix = '';
        }

        if (!empty($THEME->customcorners)) {
            // based on "_print_custom_corners_start()" (lib/weblib.php)
            if ($idbase) {
                $id = ' id="'.$idbase.'"';
                $idbt = ' id="'.$idbase.'-bt"';
                $idi1 = ' id="'.$idbase.'-i1"';
                $idi2 = ' id="'.$idbase.'-i2"';
                $idi3 = ' id="'.$idbase.'-i3"';
            } else {
                $id = '';
                $idbt = '';
                $idi1 = '';
                $idi2 = '';
                $idi3 = '';
            }
            $level = open_containers();
            $output = ''
                .'<div '.$id.'class="wrap wraplevel'.$level.' '.$classes.'">'."\n"
                .'<div '.$idbt.'class="bt"><div>&nbsp;</div></div>'."\n"
                .'<div '.$idi1.'class="i1">'
                .'<div '.$idi2.'class="i2">'
                .'<div '.$idi3.'class="i3'.$clearfix.'">'
            ;
        } else {
            if ($idbase) {
                $id = ' id="'.$idbase.'" ';
            } else {
                $id = '';
            }
            if ($classes || $clearfix) {
                $class = ' class="'.$classes.$clearfix.'"';
            } else {
                $class = '';
            }
            $output = '<div'.$id.$class.'>';
        }

        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }
}

if (! function_exists('print_container_end')) {
    // copy of "print_container_end()" (lib/weblib.php)
    function print_container_end($return=false) {
        global $THEME;

        if (empty($THEME->open_containers)) {
            debugging('Incorrect request to end container - no more open containers.', DEBUG_DEVELOPER);
            $idbase = '';
        } else {
            $idbase = array_pop($THEME->open_containers);
        }

        if (!empty($THEME->customcorners)) {
            // based on "_print_custom_corners_end()" (lib/weblib.php)
            if (idbase) {
                $idbb = ' id="'.$idbase.'-bb"';
            } else {
                $idbb = '';
            }
            $output = ''
                .'</div></div></div>'."\n"
                .'<div'.$idbb.' class="bb"><div>&nbsp;</div></div>'."\n"
                .'</div>'
            ;
        } else {
            $output = '</div>';
        }

        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }
}

if (! function_exists('addslashes_js')) {
    // based on "addslashes_js()" (lib/weblib.php)
    function addslashes_js($var) {
        static $replace_pairs = array(
            '\\' => '\\\\', "'"=>"\\'", '"' => '\\"',
            "\r\n"=>'\\n', "\r"=>'\\n', "\n"=>'\\n',
            "\0"=>'\\0',
            '</' => '<\/' // for XHTML compliance
        );
        switch (true) {
            case is_string($var):
                $var = strtr($var, $replace_pairs);
                break;
            case is_array($var):
                $var = array_map('addslashes_js', $var);
                break;
            case is_object($var):
                $vars = get_object_vars($var);
                foreach ($vars as $key=>$value) {
                    $var->$key = addslashes_js($value);
                }
                break;
        }
        return $var;
    }
}

/**
 * Javascript related defines
 */
if (! defined('REQUIREJS_BEFOREHEADER')) {
    define('REQUIREJS_BEFOREHEADER', 0);
}
if (! defined('REQUIREJS_INHEADER')) {
    define('REQUIREJS_INHEADER',     1);
}
if (! defined('REQUIREJS_AFTERHEADER')) {
    define('REQUIREJS_AFTERHEADER',  2);
}

if (! function_exists('require_js')) {
    // copy of "require_js()" (lib/weblib.php)
    function require_js($lib,$extracthtml=0) {
        global $CFG;
        static $loadlibs = array();

        static $state = REQUIREJS_BEFOREHEADER;
        static $latecode = '';

        if (!empty($lib)) {
            // Add the lib to the list of libs to be loaded, if it isn't already
            // in the list.
            if (is_array($lib)) {
                foreach($lib as $singlelib) {
                    require_js($singlelib);
                }
            } else {
                require_once $CFG->legacylibdir.'/ajax/ajaxlib.php';
                $libpath = ajax_get_lib($lib);
                if (array_search($libpath, $loadlibs) === false) {
                    $loadlibs[] = $libpath;

                    // For state other than 0 we need to take action as well as just
                    // adding it to loadlibs
                    if($state != REQUIREJS_BEFOREHEADER) {
                        // Get the script statement for this library
                        $scriptstatement=get_require_js_code(array($libpath));

                        if($state == REQUIREJS_AFTERHEADER) {
                            // After the header, print it immediately
                            print $scriptstatement;
                        } else {
                            // Haven't finished the header yet. Add it after the
                            // header
                            $latecode .= $scriptstatement;
                        }
                    }
                }
            }
        } else if($extracthtml==1) {
            if($state !== REQUIREJS_BEFOREHEADER) {
                debugging('Incorrect state in require_js (expected BEFOREHEADER): be careful not to call with empty $lib (except in print_header)');
            } else {
                $state = REQUIREJS_INHEADER;
            }

            return get_require_js_code($loadlibs);
        } else if($extracthtml==2) {
            if($state !== REQUIREJS_INHEADER) {
                debugging('Incorrect state in require_js (expected INHEADER): be careful not to call with empty $lib (except in print_header)');
                return '';
            } else {
                $state = REQUIREJS_AFTERHEADER;
                return $latecode;
            }
        } else {
            debugging('Unexpected value for $extracthtml');
        }
    }
}

if (! function_exists('get_require_js_code')) {
    // copy of "get_require_js_code()" (lib/weblib.php)
    function get_require_js_code($loadlibs) {
        global $CFG;
        // Return the html needed to load the JavaScript files defined in
        // our list of libs to be loaded.
        $output = '';
        foreach ($loadlibs as $loadlib) {
            $output .= '<script type="text/javascript" src="'.$loadlib.'"></script>'."\n";
            if ($loadlib == $CFG->wwwroot.'/lib/yui/logger/logger-min.js') {
                // Special case, we need the CSS too.
                $wwwroot = str_replace($CFG->dirroot, $CFG->wwwroot, $CFG->legacylibdir);
                $output .= '<link type="text/css" rel="stylesheet" href="'.$wwwroot.'/yui/logger/assets/logger.css" />'."\n";
            }
        }
        return $output;
    }
}

if (! function_exists('download_file_content')) {
    // based on "download_file_content()" (lib/filelib.php)
    function download_file_content($url, $headers=null, $postdata=null, $fullresponse=false, $timeout=300, $connecttimeout=20, $skipcertverify=false) {
        global $CFG;

        if (! preg_match('|^https?://|i', $url)) {
            if ($fullresponse) {
                return (object)array(
                    'status' => 0, 'headers' => array(), 'results' => '',
                    'response_code' => 'Invalid protocol specified in url',
                    'error' => 'Invalid protocol specified in url'
                );
            } else {
                return false;
            }
        }

        require_once($CFG->libdir.'/snoopy/Snoopy.class.inc');
        $snoopy = new Snoopy();

        $snoopy->read_timeout = $timeout;
        $snoopy->_fp_timeout  = $connecttimeout;
        $snoopy->proxy_host   = $CFG->proxyhost;
        $snoopy->proxy_port   = $CFG->proxyport;

        if (!empty($CFG->proxyuser) and !empty($CFG->proxypassword)) {
            // this will probably fail, but let's try it anyway
            $snoopy->proxy_user     = $CFG->proxyuser;
            $snoopy->proxy_password = $CFG->proxypassword;
        }

        $newlines = array("\r" => '', "\n" => '');
        if (is_array($headers) ) {
            $client->rawheaders = array();
            foreach ($headers as $key=>$value) {
                $client->rawheaders[$key] = strtr($value, $newlines);
            }
        }
        $url = strtr($url, $newlines);

        if (is_array($postdata)) {
            $fetch = @$snoopy->fetch($url, $postdata);
        } else {
            $fetch = @$snoopy->fetch($url);
        }

        if (! $fetch) {
            if ($fullresponse) {
                return (object)array(
                    'status' => $snoopy->status, 'headers' => array(), 'results' => '',
                    'response_code' => $snoopy->response_code, 'error' => $snoopy->error
                );
            } else {
                debugging('Snoopy request for "'.$url.'" failed with: '.$snoopy->error, DEBUG_ALL);
                return false;
            }
        }

        if ($fullresponse) {
            foreach ($snoopy->headers as $key=>$value) {
                $snoopy->headers[$key] = trim($value);
            }
            return (object)array(
                'status' => $snoopy->status, 'headers' => $snoopy->headers,
                'response_code' => trim($snoopy->response_code),
                'results' => $snoopy->results, 'error' => $snoopy->error
            );
        }

        if ($snoopy->status==200) {
            return $snoopy->results;
        } else {
            debugging('Snoopy request for "'.$url.'" failed, http response code: '.$snoopy->response_code, DEBUG_ALL);
            return false;
        }
    }
}

// utility function to add recent js and css to Moodle 1.7 and earlier
function print_formslib_js_and_css(&$mform) {
    global $CFG;

    // adjust paths and strings in mform
    $mform->_reqHTML = str_replace($CFG->pixpath, $CFG->legacypixpath, $mform->_reqHTML);
    $mform->_advancedHTML = str_replace($CFG->pixpath, $CFG->legacypixpath, $mform->_advancedHTML);
    $mform->setRequiredNote(get_string('somefieldsrequired', 'form', '<img alt="'.get_string('requiredelement', 'form').'" src="'.$CFG->legacypixpath.'/req.gif'.'" />'));

    // only print js and css once
    static $print = true;
    if ($print) {
        $print = false;

        // print javascript
        if ($CFG->majorrelease<=1.6) {
            require_js(array('yui_yahoo', 'yui_event'));
            print require_js('', 1);
            $js_file = 'formslib.mdl_16.js';
        } else {
            $js_file = 'formslib.mdl_17.js';
        }
        print '<script type="text/javascript">'."\n";
        print '//<![CDATA['."\n";
        include $CFG->legacylibdir.'/'.$js_file;
        print '//]]>'."\n";
        print '</script>'."\n";

        // print css
        print '<style type="text/css">'."\n";
        include $CFG->legacylibdir.'/formslib.css';
        print '</style>'."\n";
    }
}
?>