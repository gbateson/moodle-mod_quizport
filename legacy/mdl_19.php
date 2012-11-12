<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.9
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

if (! class_exists('database_manager')) {
    // replacement for "class database_manager" (lib/ddl/database_manager.php)
    class database_manager {
        var $mdb; // moodle_database object
        var $generator; // sql_generator object

        // constructor function
        function database_manager($mdb, $generator) {
            $this->mdb = $mdb;
            $this->generator = $generator;
        }

        function table_exists($table) {
            return table_exists($table);
        }

        function index_exists($table, $index) {
            return index_exists($table, $index);
        }

        function field_exists($table, $field) {
            return field_exists($table, $field);
        }

        function rename_field($table, $field, $newname) {
            return rename_field($table, $field, $newname);
        }

        function add_field($table, $field) {
            return add_field($table, $field);
        }

        function drop_field($table, $field) {
            return drop_field($table, $field);
        }

        function change_field_type($table, $field) {
            return change_field_type($table, $field);
        }
    }
}

if (! class_exists('moodle_database')) {
    // replacement for "class moodle_database" (lib/dml/moodle_database.php)
    class moodle_database {
        var $database_manager = null;
        var $dbfamily = null;

        // constructor function
        function moodle_database() {
            // could make use of $db to store db type
        }

        function insert_record($table, $dataobject, $returnid=true, $bulk=false) {
            return insert_record($table, $dataobject, $returnid);
        }

        function update_record($table, $dataobject, $bulk=false) {
            return update_record($table, $dataobject);
        }

        function count_records($table, $conditions=null) {
            list($field1, $value1, $field2, $value2, $field3, $value3) = $this->split_conditions($conditions);
            return count_records($table, $field1, $value1, $field2, $value2, $field3, $value3);
        }

        function count_records_select($table, $select, $params=null, $countitem='COUNT(*)') {
            return count_records_select($table, $this->merge_params($select, $params), $countitem);
        }

        function count_records_sql($sql, $params=null) {
            return count_records_sql($this->merge_params($sql, $params));
        }

        function delete_records($table, $conditions=null) {
            list($field1, $value1, $field2, $value2, $field3, $value3) = $this->split_conditions($conditions);
            return delete_records($table, $field1, $value1, $field2, $value2, $field3, $value3);
        }

        function delete_records_select($table, $select, $params=null) {
            return delete_records_select($table, $this->merge_params($select, $params));
        }

        function get_record($table, $conditions=null, $fields='*', $ignoremultiple=false) {
            list($field1, $value1, $field2, $value2, $field3, $value3) = $this->split_conditions($conditions);
            return get_record($table, $field1, $value1, $field2, $value2, $field3, $value3, $fields);
        }

        function get_record_select($table, $select, $params=null, $fields='*', $ignoremultiple=false) {
            return get_record_select($table, $this->merge_params($select, $params), $fields);
        }

        function get_record_sql($sql, $params=null, $ignoremultiple=false) {
            return get_record_sql($this->merge_params($sql, $params), $ignoremultiple);
        }

        // Moodle 1.6 and earlier use $limitfrom='', $limitnum=''
        // Moodle 1.7, 1.8 and 1.9 use $limitfrom=0, $limitnum=0

        // empty strings, '', work on all Moodle 1.9 are earlier,
        // so we use that in the following functions

        function get_records($table, $conditions=null, $sort='', $fields='*', $limitfrom='', $limitnum='') {
            list($field, $value) = $this->split_conditions($conditions, 1);
            return get_records($table, $field, $value, $sort, $fields, $limitfrom, $limitnum);
        }

        function get_records_select($table, $select, $params=null, $sort='', $fields='*', $limitfrom='', $limitnum='') {
            return get_records_select($table, $this->merge_params($select, $params), $sort, $fields, $limitfrom, $limitnum);
        }

        function get_records_select_menu($table, $select, $params=null, $sort='', $fields='*', $limitfrom='', $limitnum='') {
            return get_records_select_menu($table, $this->merge_params($select, $params), $sort, $fields, $limitfrom, $limitnum);
        }

        function get_records_sql($sql, $params=null, $limitfrom='', $limitnum='') {
            return get_records_sql($this->merge_params($sql, $params), $limitfrom, $limitnum);
        }

        function get_records_sql_menu($sql, $params=null, $limitfrom='', $limitnum='') {
            return get_records_sql_menu($this->merge_params($sql, $params), $limitfrom, $limitnum);
        }

        function get_recordset_sql($sql, $params=null, $limitfrom='', $limitnum='') {
            return get_recordset_sql($this->merge_params($sql, $params), $limitfrom, $limitnum);
        }

        function get_recordset_sql_menu($sql, $params=null, $limitfrom='', $limitnum='') {
            return get_recordset_sql_menu($this->merge_params($sql, $params), $limitfrom, $limitnum);
        }

        function get_field($table, $return, $conditions=null) {
            list($field1, $value1, $field2, $value2, $field3, $value3) = $this->split_conditions($conditions);
            return get_field($table, $return, $field1, $value1, $field2, $value2, $field3, $value3);
        }

        function get_field_select($table, $return, $select, $params=null) {
            return get_field_select($table, $return, $this->merge_params($select, $params));
        }

        function set_field($table, $newfield, $newvalue, $conditions=null) {
            list($field1, $value1, $field2, $value2, $field3, $value3) = $this->split_conditions($conditions);
            return set_field($table, $newfield, $newvalue, $field1, $value1, $field2, $value2, $field3, $value3);
        }

        function set_field_select($table, $newfield, $newvalue, $select, $params=null) {
            return set_field_select($table, $newfield, $newvalue, $this->merge_params($select, $params));
        }

        function record_exists($table, $conditions=null) {
            list($field1, $value1, $field2, $value2, $field3, $value3) = $this->split_conditions($conditions);
            return record_exists($table, $field1, $value1, $field2, $value2, $field3, $value3);
        }

        function record_exists_select($table, $select, $params=null) {
            return record_exists_select($table, $this->merge_params($select, $params));
        }

        function record_exists_sql($sql, $params=null) {
            return record_exists_sql($this->merge_params($sql, $params));
        }

        function execute($sql, $params=null) {
            return execute_sql($this->merge_params($sql, $params));
        }

        function merge_params($sql, $params) {
            global $CFG;
            static $strtr_array = array("\\"=>"\\\\", "'"=>"\\'");

            // add prefix to db table names
            // based on: lib/dml/moodle_database.php - fix_table_names()
            $sql = preg_replace('/\{([a-z]\w*)\}/', $CFG->prefix.'\\1', $sql);

            // replace values from $params
            if (is_array($params)) {
                foreach ($params as $key => $value) {
                    switch (gettype($value)) {
                        // based on: lib/adodb/adodb.inc.php (line 850)
                        case 'integer': $value = intval($value); break;
                        case 'double': $value = floatval($value); break;
                        case 'boolean': $value = ($value ? '1' : '0'); break;
                        default:
                            if ($value===null) {
                                $value = 'NULL';
                            } else {
                                if (! is_string($value)) {
                                    $value = (string)$value;
                                }
                                $value = "'".strtr($value, $strtr_array)."'";
                            }
                    }
                    if (is_numeric($key)) {
                        $sql = preg_replace('/\?/', $value, $sql, 1);
                    } else {
                        $sql = preg_replace("/:$key/", $value, $sql);
                    }
                }
            }
            return $sql;
        }

        function split_conditions($conditions, $count=3) {
            $array = array();
            $i = 0;
            if (is_array($conditions)) {
                reset($conditions);
                while (($i < $count) && (list($field, $value) = each($conditions))) {
                    $i++;
                    array_push($array, $field, $value);
                }
            }
            while ($i < $count) {
                $i++;
                array_push($array, '', '');
            }
            return $array;
        }

        function get_manager() {
            global $CFG;

            // Moodle 1.6 and earlier should not be using this method
            // use table_column() to add and rename fields
            if ($CFG->majorrelease<=1.6) {
                debugging('$DB->get_manager() method is not available on this version of Moodle', DEBUG_DEVELOPER);
                return false;
            }

            if (is_null($this->database_manager)) {
                // Moodle 1.7 and later has a ddllib.php
                require_once($CFG->libdir.'/ddllib.php');

                $classname = 'XMLDB' . $CFG->dbtype;
                $generator = new $classname();
                $generator->setPrefix($CFG->prefix);

                $this->database_manager = new database_manager($this, $generator);
            }
            return $this->database_manager;
        }

        function get_dbfamily() {
            global $CFG;
            if (is_null($this->dbfamily)) {
                switch ($CFG->dbtype) {
                    case 'mysql':
                    case 'mysqli':
                        $this->dbfamily = 'mysql';
                        break;
                    case 'postgres7':
                        $this->dbfamily = 'postgres';
                        break;
                    case 'mssql':
                    case 'mssql_n':
                    case 'odbc_mssql':
                        $this->dbfamily = 'mssql';
                        break;
                    case 'oci8po':
                        $this->dbfamily = 'oracle';
                        break;
                    default:
                        debugging('Unrecognized dbtype in mod/quizport/legacy/mdl_19: '.$CFG->dbtype, DEBUG_DEVELOPER);
                        $this->dbfamily = $CFG->dbtype;
                }
            }
            return $this->dbfamily;
        }

        function get_columns($table) {
            global $CFG, $db;
            return $db->MetaColumns("$CFG->prefix$table");
        }

        function get_tables() {
            global $CFG, $db;
            $tablenames = $db->MetaTables();

            $strlen_prefix = strlen($CFG->prefix);
            foreach ($tablenames as $i=>$tablename) {
                $tablenames[$i] = substr($tablename, $strlen_prefix);
            }

            return $tablenames;
        }

        function sql_concat() {
            global $db;
            $args = func_get_args();
            if ($this->get_dbfamily()=='postgres' && is_array($args)) {
                array_unshift($args , "''");
            }
            return call_user_func_array(array($db, 'Concat'), $args); // calls $db->Concat()
        }

        function sql_ilike() {
            if ($this->get_dbfamily()=='postgres') {
                return 'ILIKE';
            } else {
                return 'LIKE';
            }
        }

        function get_debug() {
            global $db;
            return $db->debug;
        }

        function set_debug($debug) {
            global $db;
            $db->debug = $debug;
        }
    }
}

if (! function_exists('upgrade_set_timeout')) {
    // copy of "upgrade_set_timeout()" (lib/moodlelib.php)
    function upgrade_set_timeout($max_execution_time=300) {
        global $CFG;
        if (empty($CFG->upgraderunning) || $CFG->upgraderunning < time()) {
            $upgraderunning = get_config(null, 'upgraderunning');
        } else {
            $upgraderunning = $CFG->upgraderunning;
        }
        if ($upgraderunning) {
            $max_execution_time = max(60, $max_execution_time);
            $expected_end = time() + $max_execution_time;
            if ($expected_end > ($upgraderunning + 10) || $expected_end < ($upgraderunning - 10)) {
                set_time_limit($max_execution_time);
                set_config('upgraderunning', $expected_end); // keep upgrade locked until this time
            }
        }
    }
}

if (! class_exists('progress_bar')) {
    // based on "progress_bar" (lib/weblib.php)
    class progress_bar {

        var $html_id;
        var $percent;
        var $width;
        var $clr;
        var $lastcall;
        var $time_start;
        var $minimum_time = 2; //min time between updates.

        function __construct($html_id = 'pid', $width = 500, $autostart = false){
            $this->html_id  = $html_id;
            $this->width = $width;
            $this->clr = (object)array(
                'done' => 'green', 'process' => '#FFCC66'
            );
            $this->restart();
            if($autostart){
                $this->create();
            }
        }

        function setclr($clr){
            foreach($clr as $n=>$v) {
                $this->clr->$n = $v;
            }
        }

        function create(){
                flush();
                $this->lastcall->pt = 0;
                $this->lastcall->time = microtime(true);
                $htmlcode = <<<EOT
<script type="text/javascript">
//<![CDATA[
Number.prototype.fixed=function(n){
    with(Math)
        return round(Number(this)*pow(10,n))/pow(10,n);
}
function up_{$this->html_id} (id, width, pt, msg, es){
    percent = pt*100;
    document.getElementById("status_"+id).innerHTML = msg;
    document.getElementById("pt_"+id).innerHTML =
        percent.fixed(2) + '%';
    if(percent == 100) {
        document.getElementById("progress_"+id).style.background
            = "{$this->clr->done}";
        document.getElementById("time_"+id).style.display
                = "none";
    } else {
        document.getElementById("progress_"+id).style.background
            = "{$this->clr->process}";
        if (es == Infinity){
            document.getElementById("time_"+id).innerHTML =
                "Initializing...";
        }else {
            document.getElementById("time_"+id).innerHTML =
                es.fixed(2)+" sec";
            document.getElementById("time_"+id).style.display
                = "block";
        }
    }
    document.getElementById("progress_"+id).style.width
        = width + "px";

}
//]]>
</script>
<div style="text-align:center;width:{$this->width}px;clear:both;padding:0;margin:0 auto;">
    <h2 id="status_{$this->html_id}" style="text-align: center;margin:0 auto"></h2>
    <p id="time_{$this->html_id}"></p>
    <div id="bar_{$this->html_id}" style="border-style:solid;border-width:1px;width:500px;height:50px;">
        <div id="progress_{$this->html_id}"
        style="text-align:center;background:{$this->clr->process};width:4px;border:1px
        solid gray;height:38px; padding-top:10px;">&nbsp;<span id="pt_{$this->html_id}"></span>
        </div>
    </div>
</div>
EOT;
                echo $htmlcode;
                flush();
        }

        function _update($percent, $msg, $estimate){
            if(empty($this->time_start)){
                $this->time_start = microtime(true);
            }
            $this->percent = $percent;
            $this->lastcall->time = microtime(true);
            $this->lastcall->pt   = $percent;
            $w = $this->percent * $this->width;
            if ($estimate === null){
                $estimate = "Infinity";
            }
            echo "<script type=\"text/javascript\">up_".$this->html_id."('$this->html_id', '$w', '$this->percent', '$msg', $estimate);</script>";
            flush();
        }

        function estimate($curtime, $pt){
            $consume = $curtime - $this->time_start;
            $one = $curtime - $this->lastcall->time;
            $this->percent = $pt;
            $percent = $pt - $this->lastcall->pt;
            if (! $percent) {
                return 0;
            }
            $left = ($one / $percent) - $consume;
            if($left < 0) {
                return 0;
            }
            return $left;
        }

        function update_full($percent, $msg){
            $percent = max(min($percent, 100), 0);
            if ($percent==100 || ($this->lastcall->time + $this->minimum_time) <= microtime(true)){
                $this->_update($percent/100, $msg);
            }
        }

        function update($cur, $total, $msg){
            $cur = max($cur, 0);
            if ($cur >= $total) {
                $percent = 1;
            } else {
                $percent = $cur / $total;
            }
            $estimate = $this->estimate(microtime(true), $percent);
            $this->_update($percent, $msg, $estimate);
        }

        function restart(){
            $this->percent  = 0;
            $this->lastcall = (object)array(
                'pt' => 0, 'time' => microtime(true), 'time_start' => 0
            );
        }
    }
}

// get page and block libraries
if ($CFG->majorrelease<=1.4) {
    $CFG->legacylibdir = $CFG->dirroot.'/mod/quizport/legacy/lib';
    require_once($CFG->legacylibdir.'/pagelib.php');
    require_once($CFG->legacylibdir.'/blocklib.php');
    // Moodle 1.3 and 1.4 have some functions from their own blocklib.php
    // so the above file must only define functions that do not already exist
} else {
    require_once($CFG->libdir.'/pagelib.php');
    require_once($CFG->libdir.'/blocklib.php');
    require_once($CFG->dirroot.'/course/lib.php'); // needed for some blocks
}

/**
 * Class that models the behavior of a quizport page
 *
 * @author  Gordon Bateson
 * @package pages
 */

class page_quizport extends page_generic_activity {
    var $activityname = 'quizport';

    function get_type() {
        return QUIZPORT_PAGEID;
    }

    function init_full() {
        if ($this->full_init_done) {
            return;
        }
        $this->full_init_done = true;

        global $QUIZPORT, $USER;

        $this->courserecord = &$QUIZPORT->courserecord;
        $this->modulerecord = &$QUIZPORT->modulerecord;
        $this->activityrecord = &$QUIZPORT->activityrecord;

        if (! empty($QUIZPORT->modulecontext)) {
            $this->allowed_editing = has_capability('mod/quizport:manage', $QUIZPORT->modulecontext);
        } else if (! empty($QUIZPORT->coursecontext)) {
            $this->allowed_editing = has_capability('moodle/course:manageactivities', $QUIZPORT->coursecontext);
        } else {
            $this->allowed_editing = false;
        }

        $edit = optional_param('edit', -1, PARAM_BOOL);
        if ($edit != -1 && $this->allowed_editing) {
            $USER->editing = $edit;
            $this->is_editing = $edit;
        } else if (isset($USER->editing)) {
            $this->is_editing = $USER->editing;
        } else {
            $this->is_editing = isediting($this->courserecord->id);
        }
    }

    function url_get_path() {
        global $CFG;
        return $CFG->wwwroot .'/'.str_replace('-', '/', QUIZPORT_PAGEID).'.php';
    }

    function user_allowed_editing() {
        $this->init_full();
        return $this->allowed_editing;
    }

    function user_is_editing() {
        $this->init_full();
        return $this->is_editing;
    }

    // Given an instance of a block in this page and the direction in which we want to move it,
    // where is it going to go? Return the identifier of the instance's new position.
    function blocks_move_position(&$block, $move) {
        if($block->position == BLOCK_POS_LEFT && $move == BLOCK_MOVE_RIGHT) {
            // allow this block to move right
            return BLOCK_POS_RIGHT;
        }
        if ($block->position == BLOCK_POS_RIGHT && $move == BLOCK_MOVE_LEFT) {
            // allow this block to move left
            return BLOCK_POS_LEFT;
        }
        // no move is necessary (block is already ready where we want it to be)
        return $block->position;
    }

    function print_header($title, $navlinks=null, $bodytags='', $meta='') {
        global $CFG;
        $this->init_full();
        if ($this->activityrecord && empty($navlinks) && $this->user_allowed_editing()) {
            $buttons = '<table><tr><td>'.update_module_button($this->modulerecord->id, $this->courserecord->id, get_string('modulename', $this->activityname)).'</td>';
            if (! empty($CFG->showblocksonmodpages)) {
                if ($this->user_is_editing()) {
                    $edit = 'off';
                } else {
                    $edit = 'on';
                }
                $buttons .= ''
                    .'<td><form '.$CFG->frametarget.' method="get" action="view.php"><div>'
                    .'<input type="hidden" name="id" value="'.$this->modulerecord->id.'" />'
                    .'<input type="hidden" name="edit" value="'.$edit.'" />'
                    .'<input type="submit" value="'.get_string('blocksedit'.$edit).'" />'
                    .'</div></form></td>'
                ;
            }
            $buttons .= '</tr></table>';
        } else {
            $buttons = '&nbsp;';
        }
        if (empty($navlinks)) {
            $navlinks = array();
        }
        if ($this->modulerecord) {
            $navigation = build_navigation($navlinks, $this->modulerecord);
        } else {
            $navigation = build_navigation($navlinks, $this->courserecord);
        }
        print_header($title, $this->courserecord->fullname, $navigation, '', $meta, true, $buttons, navmenu($this->courserecord, $this->modulerecord), false, $bodytags);
    }
}

// get CSS id and class for this page
// e.g. mod-quizport-view & mod-quizport
page_id_and_class($pageid, $pageclass);
if ($pageclass=='backup') {
    $pageid = 'mod-quizport-view';
}

// map page id to php class for this page
if ($pageclass=='course') {
    // do nothing - we are on the course page
} else {
    $DEFINEDPAGES = array($pageid);
    define('QUIZPORT_PAGEID', $pageid);
    define('QUIZPORT_PAGECLASS', $pageclass);
    page_map_class($pageid, 'page_quizport');
}

// tidy up
unset($pageid, $pageclass);

// set up $DB object, if necessary
if (empty($GLOBALS['DB'])) {
    $GLOBALS['DB'] = new moodle_database();
}
?>