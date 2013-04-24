<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.3
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

if (! defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', true);
}

// PARAM settings (lib/moodlelib.php)
if (! defined('PARAM_RAW')) {
    define('PARAM_RAW',      0x0000);
}
if (! defined('PARAM_CLEAN')) {
    define('PARAM_CLEAN',    0x0001);
}
if (! defined('PARAM_INT')) {
    define('PARAM_INT',      0x0002);
}
if (! defined('PARAM_INTEGER')) {
    define('PARAM_INTEGER',  0x0002);
}
if (! defined('PARAM_ALPHA')) {
    define('PARAM_ALPHA',    0x0004);
}
if (! defined('PARAM_ACTION')) {
    define('PARAM_ACTION',   0x0004);
}
if (! defined('PARAM_FORMAT')) {
    define('PARAM_FORMAT',   0x0004);
}
if (! defined('PARAM_NOTAGS')) {
    define('PARAM_NOTAGS',   0x0008);
}
if (! defined('PARAM_FILE')) {
    define('PARAM_FILE',     0x0010);
}
if (! defined('PARAM_PATH')) {
    define('PARAM_PATH',     0x0020);
}
if (! defined('PARAM_HOST')) {
    define('PARAM_HOST',     0x0040);
}
if (! defined('PARAM_URL')) {
    define('PARAM_URL',      0x0080);
}
if (! defined('PARAM_LOCALURL')) {
    define('PARAM_LOCALURL', 0x0180);
}
if (! defined('PARAM_CLEANFILE')) {
    define('PARAM_CLEANFILE',0x0200);
}
if (! defined('PARAM_ALPHANUM')) {
    define('PARAM_ALPHANUM', 0x0400);
}
if (! defined('PARAM_BOOL')) {
    define('PARAM_BOOL',     0x0800);
}

if (! function_exists('required_param')) {
    function required_param($varname, $options=PARAM_CLEAN) {
        if (isset($_POST[$varname])) {
            return clean_param($_POST[$varname], $options);
        }
        if (isset($_GET[$varname])) {
            return clean_param($_GET[$varname], $options);
        }
        error('A required parameter ('.$varname.') was missing');
    }
}

if (! function_exists('optional_param')) {
    function optional_param($varname, $default=NULL, $options=PARAM_CLEAN) {
        if (isset($_POST[$varname])) {
            return clean_param($_POST[$varname], $options);
        }
        if (isset($_GET[$varname])) {
            return clean_param($_GET[$varname], $options);
        }
        return $default;
    }
}

if (! function_exists('clean_param')) {
    function clean_param($param, $options) {
    /// Given a parameter and a bitfield of options, this function
    /// will clean it up and give it the required type, etc.

        global $CFG;

        if (!$options) {
            return $param;                   // Return raw value
        }

        if ((string)$param == (string)(int)$param) {  // It's just an integer
            return $param;
        }

        if ($options & PARAM_CLEAN) {
            $param = clean_text($param, FORMAT_MOODLE);     // Sweep for scripts, etc
        }

        if ($options & PARAM_INT) {
            $param = (int)$param;            // Convert to integer
        }

        if ($options & PARAM_ALPHA) {        // Remove everything not a-zA-Z, coverts to lowercase
            $param = preg_replace('/[^a-zA-Z]/', '', $param);
        }

        if ($options & PARAM_ALPHANUM) {     // Remove everything not a-zA-Z0-9
            $param = preg_replace('/[^a-zA-Z0-9]/', '', $param);
        }

        if ($options & PARAM_BOOL) {         // Convert to 1 or 0
            $param = empty($param) ? 0 : 1;
        }

        if ($options & PARAM_NOTAGS) {       // Strip all tags completely
            $param = strip_tags($param);
        }

        if ($options & PARAM_CLEANFILE) {    // allow only safe characters
            $param = clean_filename($param);
        }

        if ($options & PARAM_FILE) {         // Strip all suspicious characters from filename
            $param = preg_replace('/[[:cntrl:]]|[<>"`\|\':\\/]/', '', $param);
            $param = preg_replace('/\.\.+/', '', $param);
        }

        if ($options & PARAM_PATH) {         // Strip all suspicious characters from file path
            $param = str_replace('\\\'', '\'', $param);
            $param = str_replace('\\"', '"', $param);
            $param = str_replace('\\', '/', $param);
            $param = preg_replace('/[[:cntrl:]]|[<>"`\|\':]/', '', $param);
            $param = preg_replace('/\.\.+/', '', $param);
            $param = preg_replace('/\/\/+/', '/', $param);
        }

        if ($options & PARAM_HOST) {         // allow FQDN or IPv4 dotted quad
            preg_replace('/[^\.\d\w-]/','', $param ); // only allowed chars
            // match ipv4 dotted quad
            if (preg_match('/(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/',$param, $match)){
                // confirm values are ok
                if ( $match[0] > 255
                     || $match[1] > 255
                     || $match[3] > 255
                     || $match[4] > 255 ) {
                    // hmmm, what kind of dotted quad is this?
                    $param = '';
                }
            } elseif ( preg_match('/^[\w\d\.-]+$/', $param) // dots, hyphens, numbers
                       && !preg_match('/^[\.-]/',  $param) // no leading dots/hyphens
                       && !preg_match('/[\.-]$/',  $param) // no trailing dots/hyphens
                       ) {
                // all is ok - $param is respected
            } else {
                // all is not ok...
                $param='';
            }
        }

        if ($options & PARAM_URL) { // allow safe ftp, http, mailto urls

            include_once($CFG->legacylibdir . '/validateurlsyntax.php');

            //
            // Parameters to validateurlsyntax()
            //
            // s? scheme is optional
            //   H? http optional
            //   S? https optional
            //   F? ftp   optional
            //   E? mailto optional
            // u- user section not allowed
            //   P- password not allowed
            // a? address optional
            //   I? Numeric IP address optional (can use IP or domain)
            //   p-  port not allowed -- restrict to default port
            // f? "file" path section optional
            //   q? query section optional
            //   r? fragment (anchor) optional
            //
            if (!empty($param) && validateUrlSyntax($param, 's?H?S?F?E?u-P-a?I?p-f?q?r?')) {
                // all is ok, param is respected
            } else {
                $param =''; // not really ok
            }
            $options ^= PARAM_URL; // Turn off the URL bit so that simple PARAM_URLs don't test true for PARAM_LOCALURL
        }

        if ($options & PARAM_LOCALURL) {
            // assume we passed the PARAM_URL test...
            // allow http absolute, root relative and relative URLs within wwwroot
            if (!empty($param)) {
                if (preg_match(':^/:', $param)) {
                    // root-relative, ok!
                } elseif (preg_match('/^'.preg_quote($CFG->wwwroot, '/').'/i',$param)) {
                    // absolute, and matches our wwwroot
                } else {
                    // relative - let's make sure there are no tricks
                    if (validateUrlSyntax($param, 's-u-P-a-p-f+q?r?')) {
                        // looks ok.
                    } else {
                        $param = '';
                    }
                }
            }
        }

        return $param;
    }
}

if (! function_exists('confirm_sesskey')) {
    function confirm_sesskey($sesskey=NULL) {
        global $USER;
        if (! $user_sesskey = sesskey()) {
            return false;
        }
        if (empty($sesskey)) {
            $sesskey = required_param('sesskey');
        }
        return ($user_sesskey==$sesskey);
    }
}

if (! function_exists('sesskey')) {
    function sesskey() {
        global $USER;
        if (empty($USER)) {
            return false;
        }
        if (empty($USER->sesskey)) {
            $USER->sesskey = random_string(10);
        }
        return $USER->sesskey;
    }
}

if (! function_exists('get_field_sql')) {
    function get_field_sql($sql) {

        global $db, $CFG;

        $rs = $db->Execute($sql);
        if (!$rs) {
            if (isset($CFG->debug) and $CFG->debug > 7) {
                notify($db->ErrorMsg()."<br /><br />$sql");
            }
            return false;
        }

        if ( $rs->RecordCount() == 1 ) {
            return $rs->fields[0];
        } else {
            return false;
        }
    }
}

function quizport_helpbutton($page, $title='', $module='moodle', $image=true, $linktext=false, $text='', $return=false) {
    if ($return) {
        ob_start();
        helpbutton($page, $title, $module, $image, $linktext, $text);
        return ob_get_clean();
    }
    helpbutton($page, $title, $module, $image, $linktext, $text);
}

function quizport_link_to_popup_window($url, $name='popup', $linkname='click here', $height=400, $width=500, $title='Popup window', $options='none', $return=false) {
    if ($return) {
        ob_start();
        link_to_popup_window($url, $name, $linkname, $height, $width, $title, $options);
        return ob_get_clean();
    }
    link_to_popup_window($url, $name, $linkname, $height, $width, $title, $options);
}

// set missing strings for Moodle 1.3
if (empty($CFG->quizport_missingstrings_mdl_13)) {
    $missingstrings['mdl_13'] = array(
        'en_utf8' => array(
            'lesson' => array(
                'accesscontrol' => 'Access control'
            ),
            'quiz' => array(
                'preview' => 'Preview'
            ),
            'resource' => array(
                'display' => 'Display',
                'pagewindow' => 'Same window',
                'searchweb' => 'Search for web page'
            )
        )
    );
}
?>