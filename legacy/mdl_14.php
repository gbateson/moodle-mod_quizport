<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.4
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// PARAM settings (lib/moodlelib.php)
if (! defined('PARAM_CLEANHTML')) {
    define('PARAM_CLEANHTML',0x1000);
}
if (! defined('PARAM_ALPHAEXT')) {
    define('PARAM_ALPHAEXT', 0x2000);
}
if (! defined('PARAM_SAFEDIR')) {
    define('PARAM_SAFEDIR',  0x4000);
}
if (! defined('PAGE_COURSE_VIEW')) {
    define('PAGE_COURSE_VIEW', 'course-view');
}

if (! defined('HOURSECS')) {
    define('HOURSECS', 3600);
}
if (! defined('MINSECS')) {
    define('MINSECS', 60);
}

if (! function_exists('current_theme')) {
    // copy of current_theme() (lib/weblib.php) Moodle 1.5
    function current_theme() {
        global $CFG, $USER, $SESSION, $course;

        if (!empty($CFG->pagetheme)) {  // Page theme is for special page-only themes set by code
            return $CFG->pagetheme;

        } else if (!empty($CFG->coursetheme) and !empty($CFG->allowcoursethemes)) {  // Course themes override others
            return $CFG->coursetheme;

        } else if (!empty($SESSION->theme)) {    // Session theme can override other settings
            return $SESSION->theme;

        } else if (!empty($USER->theme) and !empty($CFG->allowuserthemes)) {    // User theme can override site theme
            return $USER->theme;

        } else {
            return $CFG->theme;
        }
    }
}

if (! function_exists('format_string')) {
    // copy of format_string() (lib/weblib.php) Moodle 1.5
    function format_string($string, $striplinks=false, $courseid=null ) {

        global $CFG, $course;

        //We'll use a in-memory cache here to speed up repeated strings
        static $strcache;

        //Calculate md5
        $md5 = md5($string.'<+>'.$striplinks);

        //Fetch from cache if possible
        if (isset($strcache[$md5])) {
            return $strcache[$md5];
        }

        if (empty($courseid)) {
            if (!empty($course->id)) {         // An ugly hack for better compatibility
                $courseid = $course->id;       // (copied from format_text)
            }
        }

        if (!empty($CFG->filterall)) {
            $string = filter_text($string, $courseid);
        }

        if ($striplinks) {  //strip links in string
            $string = preg_replace('/(<a[^>]+?>)(.+?)(<\/a>)/is','$2',$string);
        }

        //Store to cache
        $strcache[$md5] = $string;

        return $string;
    }
}

if (! function_exists('page_id_and_class')) {
    // copy of page_id_and_class() (lib/weblib.php) Moodle 1.5
    function page_id_and_class(&$getid, &$getclass) {
        // Create class and id for this page
        global $CFG, $ME;

        static $class = null;
        static $id    = null;

        if (empty($class) || empty($id)) {
            if (isset($CFG->httpswwwroot)) {
                $path = str_replace($CFG->httpswwwroot.'/', '', $ME);
            }
            $path = str_replace($CFG->wwwroot.'/', '', $ME);
            $path = str_replace('.php', '', $path);
            if (substr($path, -1) == '/') {
                $path .= 'index';
            }
            if (empty($path) || $path == 'index') {
                $id    = 'site-index';
                $class = 'course';
            } else {
                $id    = str_replace('/', '-', $path);
                $class = explode('-', $id);
                array_pop($class);
                $class = implode('-', $class);
            }
        }

        $getid    = $id;
        $getclass = $class;
    }
}

if (! function_exists('print_checkbox')) {
    // replacement for print_checkbox() (lib/weblib.php) Moodle 1.5
    function print_checkbox($name, $value, $checked=true, $label='', $alt='') {

        static $counter = 0;
        $id = 'auto-cb'.sprintf('%04d', ++$counter);

        if (! $name) {
            $name = 'unnamed';
        }
        if (! $alt) {
            $alt = 'checkbox';
        }
        if ($checked) {
            $strchecked = ' checked="checked"';
        } else {
            $strchecked = '';
        }
        if ($label) {
            $label = ' <label for="'.$id.'">'.$label.'</label>';
        }

        print ''
            .'<span class="checkbox '.$name.'">'
            .'<input name="'.$name.'" id="'.$id.'" type="checkbox" value="'.$value.'" alt="'.$alt.'"'.$strchecked.' />'
            .$label
            .'</span>'."\n"
        ;
    }
}

if (! function_exists('choose_from_radio')) {
    // copy of choose_from_radio() (lib/weblib.php) Moodle 1.5
    function choose_from_radio($options, $name, $checked='') {

        static $idcounter = 0;

        if (! $name) {
            $name = 'unnamed';
        }

        $output = '<span class="radiogroup '.$name.'">'."\n";

        if (! empty($options)) {
            $currentradio = 0;
            foreach ($options as $value => $label) {
                if ($label==='') {
                    $label = $value;
                }
                if ($value==$checked) {
                    $strchecked = ' checked="checked"';
                } else {
                    $strchecked = '';
                }
                $id = 'auto-rb'.sprintf('%04d', ++$idcounter);
                $output .= ' '
                    .'<span class="radioelement '.$name.' rb'.$currentradio.'">'
                    .'<input name="'.$name.'" id="'.$id.'" type="radio" value="'.$value.'"'.$strchecked.' /> '
                    .'<label for="'.$id.'">'.$label.'</label></span>'."\n"
                ;
                $currentradio = ($currentradio + 1) % 2;
            }
        }
        $output .= '</span>'."\n";
        echo $output;
    }
}

if (! function_exists('close_window')) {
    // copy of  close_window() (lib/weblib.php) Moodle 1.5
    function close_window($delay=0) {
        echo '<script language="javascript" type="text/javascript">'."\n";
        echo '//<![CDATA['."\n";
        if ($delay) {
            sleep($delay);
        }
        echo 'self.close();'."\n";
        echo '//]]>'."\n";
        echo '</script>'."\n";
        exit;
    }
}

if (! class_exists('tabobject')) {
    /// A class for tabs
    class tabobject {
        var $id;
        var $link;
        var $text;
        var $linkedwhenselected;

        /// A constructor just because I like constructors
        function tabobject ($id, $link='', $text='', $linkedwhenselected=false) {
            $this->id   = $id;
            $this->link = $link;
            $this->text = $text;
            $this->linkedwhenselected = $linkedwhenselected;
        }


        /// a method to look after the messy business of setting up a tab cell
        /// with all the appropriate classes and things
        function createtab ($selected=false, $inactive=false, $activetwo=false, $last=false) {
            $str  = '';
            $astr = '';
            $cstr = '';

        /// The text and anchor for this tab
            if ($inactive || $activetwo || ($selected && !$this->linkedwhenselected) ) {
                $astr .= $this->text;
            } else {
                $astr .= '<a href="'.$this->link.'" title="'.$this->text.'">'.$this->text.'</a>';
            }

        /// There's an IE bug with background images in <a> tags
        /// so we put a div around so that we can add a background image
            $astr = '<div class="tablink">'.$astr.'</div>';

        /// Set the class for inactive cells
            if ($inactive) {
                $cstr .= ' inactive';

            /// Set the class for active cells in the second row
                if ($activetwo) {
                    $cstr .= ' activetwo';
                }

        /// Set the class for the selected cell
            } else if ($selected) {
                $cstr .= ' selected';

        /// Set the standard class for a cell
            } else {
                $cstr .= ' active';
            }


        /// Are we on the last tab in this row?
            if ($last) {
                $astr = '<div class="last">'.$astr.'</div>';
            }

        /// Lets set up the tab cell
            $str .= '<td';
            if (!empty($cstr)) {
                $str .= ' class="'.ltrim($cstr).'"';
            }
            $str .= '>';
            $str .= $astr;
            $str .= '</td>';

            return $str;
        }
    }
}

if (! function_exists('print_tabs')) {
    // replacement for tabs() (lib/weblib.php) Moodle 1.5
    function print_tabs($tabrows, $selected=null, $inactive=null, $activetwo=null, $return=false) {
        global $CFG;

        if (is_null($inactive)) {
            $inactive = array();
        }

        if (is_null($activetwo)) {
            $activetwo = array();
        }

    /// Bring the row with the selected tab to the front
        if (!empty($CFG->tabselectedtofront) && ($selected !== null) ) {
            $found = false;
            $frontrows = array();
            $rearrows  = array();
            foreach ($tabrows as $row) {
                if ($found) {
                    $rearrows[] = $row;
                } else {
                    foreach ($row as $tab) {
                        if ($found) {
                            continue;
                        }
                        $found = ($selected == $tab->id);
                    }
                    $frontrows[] = $row;
                }
            }
            $tabrows = array_merge($rearrows,$frontrows);
        }

        //$filepath = $CFG->legacylib.'/tabs.css';
        //if (is_file($filepath)) {
        //    if (function_exists('file_get_contents')) {
        //        $str = file_get_contents($filepath)."\n";
        //    } else {
        //        $str = file($filepath);
        //        if (is_array($str)) {
        //             $str = implode('', $str);
        //        }
        //    }
        //    $str = '<style type="text/css">'."\n".$str."\n</style>\n"
        //}
        //    $str = '';
        //}
        $str = '<link type="text/css" rel="stylesheet" href="'.$CFG->wwwroot.'/mod/quizport/legacy/lib/tabs.css.php" />'."\n";

        $str .= '<table class="tabs" cellspacing="0">';
        $str .= '<tr><td class="left side"></td><td>';

        $rowcount = count($tabrows);
        foreach ($tabrows as $row) {

            $str .= '<table class="tabrow r'.($rowcount--).'" cellspacing="0">';
            $str .= '<tr>';

            $numberoftabs = count($row);
            $currenttab   = 0;

            foreach ($row as $tab) {
                $currenttab++;
                $str .= $tab->createtab( ($selected == $tab->id), (in_array($tab->id, $inactive)), (in_array($tab->id, $activetwo)), ($currenttab == $numberoftabs) );
            }

            $str .= '</tr>';
            $str .= '</table>';
        }
        $str .= '</td><td class="right side"></td></tr>';
        $str .= '</table>';

        if ($return) {
            return $str;
        } else {
            echo $str;
        }
    }
}

if (! function_exists('print_error')) {
    // copy of print_error() (lib/weblib.php) Moodle 1.5
    function print_error ($string, $link='') {
        $string = get_string($string, 'error');
        error($string, $link);
    }
}

if (! function_exists('sql_fullname')) {
    // copy of sql_fullname() (lib/weblib.php) Moodle 1.5
    function sql_fullname($firstname='firstname', $lastname='lastname') {
        global $CFG;
        switch ($CFG->dbtype) {
            case 'mysql':
                return ' CONCAT('.$firstname.'," ",'.$lastname.') ';
            case 'postgres7':
                return " $firstname||' '||$lastname ";
            default:
                return ' '.$firstname.'||" "||'.$lastname.' ';
        }
    }
}

if (! function_exists('sql_ilike')) {
    // copy of sql_ilike() (lib/datalib.php) Moodle 1.5
    function sql_ilike() {
        global $CFG;
        switch ($CFG->dbtype) {
            case 'mysql':
                 return 'LIKE';
            default:
                 return 'ILIKE';
        }
    }
}

// set missing strings for Moodle 1.4
if (empty($CFG->quizport_missingstrings_mdl_14)) {
    $missingstrings['mdl_14'] = array(
        'en_utf8' => array(
            'quiz' => array(
                'answers' => 'Answers',
                'info' => 'Info',
                'responses' => 'Responses',
                'scores' => 'Scores'
            ),
            'moodle' => array(
                'default' => 'Default'
            )
        )
    );
}
?>