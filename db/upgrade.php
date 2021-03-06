<?php // $Id$

// This file keeps track of upgrades to the quizport module

// standardize Moodle API (in particular we need $DB)
require_once($CFG->dirroot.'/mod/quizport/legacy.php');

function xmldb_quizport_upgrade($oldversion=0, $module=null) {

    global $CFG, $DB, $THEME;
    $dbman = $DB->get_manager();
    $result = true;

    // if this flag is set to true, the QuizPort cache
    // will be emptied in the last step of the upgrade
    $empty_cache = false;

    // if this flag is set to true, the quizport_missingstrings_mdl_xx
    // config settings will be unset at the end of the upgrade
    $unset_strings = false;

    if ($CFG->majorrelease<=1.9) {
        // Moodle 1.7 - 1.9
        $xmldb_table_class = 'XMLDBTable';
        $xmldb_field_class = 'XMLDBField';
    } else {
        // Moodle 2.0
        $xmldb_table_class = 'xmldb_table';
        $xmldb_field_class = 'xmldb_field';
    }

    if (function_exists('rs_fetch_next_record')) {
        // Moodle <= 1.9
        $next = 'rs_fetch_next_record';
        $close = 'Close';
    } else {
        // Moodle >= 2.0
        $next = 'next';
        $close = 'close';
    }

    // check the indexes are all in order
    $newversion = 2008033101;
    if ($result && $oldversion < $newversion) {
        $result = xmldb_quizport_check_indexes($result);
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033105;
    if ($result && $oldversion < $newversion) {
        $table = new $xmldb_table_class('quizport_cache');
        $fields = array(
            // $thisfield => $previousfield
            'sourcelastmodified' => 'sourcelocation',
            'sourceetag' => 'sourcelastmodified',
            'configlastmodified' => 'configlocation',
            'configetag' => 'configlastmodified'
        );
        foreach($fields as $thisfield => $previousfield) {
            $field = new $xmldb_field_class($thisfield);
            if (! $dbman->field_exists($table, $field)) {
                xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, $previousfield);
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033106;
    if ($result && $oldversion < $newversion) {
        if (isset($CFG->quizport_popupoptions)) {
            unset_config('quizport_popupoptions');
        }
        $options = array(
            'resizable', 'scrollbars', 'directories', 'location',
            'menubar', 'toolbar', 'status', 'width', 'height'
        );
        foreach ($options as $option) {
            $popupoption = "quizport_popup$option";
            if (isset($CFG->$popupoption)) {
                unset_config($popupoption);
            }
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033107;
    if ($result && $oldversion < $newversion) {
        set_config('quizport_maxeventlength', 5); // 5 days
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033108;
    if ($result && $oldversion < $newversion) {
        $table = new $xmldb_table_class('quizport_conditions');

        $field = new $xmldb_field_class('attempttime');
        if ($dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $dbman->rename_field($table, $field, 'attemptduration');
        }

        $field = new $xmldb_field_class('attemptdelay');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'attemptduration');
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033112;
    if ($result && $oldversion < $newversion) {
        $actions = array(
            'editcolumnlists', 'editcondition', 'editquiz', 'editquizzes', 'report', 'submit', 'view'
        );
        foreach($actions as $action) {
            $record = (object)array(
                'module'=>'quizport', 'action'=>$action, 'mtable'=>'quizport', 'field'=>'name'
            );
            if ($record->id = $DB->get_field('log_display', 'id', array('module'=>'quizport', 'action'=>$action))) {
                if (! $DB->update_record('log_display', $record)) {
                    debugging(get_string('error_updaterecord', 'quizport', "log_display (id=$record->id)"), DEBUG_DEVELOPER);
                }
            } else {
                if (! $DB->insert_record('log_display', $record)) {
                    debugging(get_string('error_insertrecord', 'quizport', 'log_display'), DEBUG_DEVELOPER);
                }
            }
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033113;
    if ($result && $oldversion < $newversion) {
        // standardize nextquizid on pre-conditions and conditionquizid on post-conditions
        $DB->execute('UPDATE {quizport_conditions} SET nextquizid=quizid WHERE conditiontype=1');
        $DB->execute('UPDATE {quizport_conditions} SET conditionquizid=quizid WHERE conditiontype=2');
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033114;
    if ($result && $oldversion < $newversion) {
        if ($logs = $DB->get_records_select('log', "course=0 AND module='quizport'")) {
            $debug = $DB->get_debug();
            $DB->set_debug(false);
            foreach ($logs as $log) {
                if (preg_match('/\w+.php\?id=(\d+)/', $log->url, $matches)) {
                    $id = intval($matches[1]);
                } else {
                    $id = 0;
                }
                if ($id==0) {
                    if (! $DB->delete_records_select('log', "id=$log->id")) {
                        debugging(get_string('error_deleterecords', 'quizport', "log (id=$log->id)"), DEBUG_DEVELOPER);
                    }
                    continue;
                }
                switch ($log->action) {
                    case 'editcondition':
                        // $id is conditionid
                        $tables = '{quizport_conditions} qc,{quizport_quizzes} qq,{quizport_units} qu,{quizport} q,{course_modules} cm,{modules} m';
                        $fields = 'qc.id AS id, q.id AS quizportid, cm.course AS courseid, cm.id AS coursemoduleid';
                        $select = "qc.id=$id AND qc.quizid=qq.id AND qq.unitid=qu.id AND qu.parenttype=0 AND qu.parentid=q.id AND q.id=cm.instance AND cm.module=m.id AND m.name='quizport'";
                        break;
                    case 'editquiz':
                        // $id is quizid
                        $tables = '{quizport_quizzes} qq,{quizport_units} qu,{quizport} q,{course_modules} cm,{modules} m';
                        $fields = 'qq.id AS id, q.id AS quizportid, cm.course AS courseid, cm.id AS coursemoduleid';
                        $select = "qq.id=$id AND qq.unitid=qu.id AND qu.parenttype=0 AND qu.parentid=q.id AND q.id=cm.instance AND cm.module=m.id AND m.name='quizport'";
                        break;
                    case 'editquizzes':
                    case 'report':
                    case 'submit':
                    case 'view':
                        // $id is coursemoduleid
                        $tables = '{course_modules} cm,{quizport} q,{modules} m';
                        $fields = 'cm.id AS id, cm.course AS courseid, cm.id AS coursemoduleid, q.id AS quizportid';
                        $select = "cm.id=$id AND cm.instance=q.id AND cm.module=m.id AND m.name='quizport'";
                        break;
                }
                if (! $record = $DB->get_record_sql("SELECT $fields FROM $tables WHERE $select")) {
                    if (! $DB->delete_records_select('log', "id=$log->id")) {
                        debugging(get_string('error_deleterecords', 'quizport', "log (id=$log->id)"), DEBUG_DEVELOPER);
                    }
                    continue;
                }
                $log->course = $record->courseid;
                $log->info = $record->quizportid;
                $log->cm = $record->coursemoduleid;
                if (! $DB->update_record('log', $log)) {
                    debugging(get_string('error_updaterecord', 'quizport', "log (id=$log->id)"), DEBUG_DEVELOPER);
                }
            }
            $DB->set_debug($debug);
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033116;
    if ($result && $oldversion < $newversion) {
        // quizport_cache: rename "outputformat" field to "navigation"
        $table = new $xmldb_table_class('quizport_cache');
        $field = new $xmldb_field_class('outputformat');
        if ($dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $dbman->rename_field($table, $field, 'navigation');
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033117;
    if ($result && $oldversion < $newversion) {

        // add field: quizport_units.attemptgrademethod
        $table = new $xmldb_table_class('quizport_units');
        $field = new $xmldb_field_class('attemptgrademethod');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'attemptlimit');
            $dbman->add_field($table, $field);

            // transfer values from quizzes.scorepriority
            $unitids  = 'SELECT DISTINCT unitid FROM {quizport_quizzes} WHERE scorepriority=1';
            $DB->execute('UPDATE {quizport_units} SET attemptgrademethod=1 WHERE id IN ('.$unitids.')');
        }

        // drop field: quizport_quizzes.scorepriority
        $table = new $xmldb_table_class('quizport_quizzes');
        $field = new $xmldb_field_class('scorepriority');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033120;
    if ($result && $oldversion < $newversion) {
        $table = new $xmldb_table_class('quizport_units');

        // rename old "intro" fields to "entry" field

        $field = new $xmldb_field_class('showintro');
        if ($dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $dbman->rename_field($table, $field, 'entrypage');
        }

        $field = new $xmldb_field_class('intro');
        if ($dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_TEXT, 'small');
            $dbman->rename_field($table, $field, 'entrytext');
        }

        // add new fields for "entry" and "exit" pages

        $field = new $xmldb_field_class('entryoptions');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'entrytext');
            $dbman->add_field($table, $field);
            $DB->set_field('quizport_units', 'entryoptions', 0x0F); // =15
        }

        $field = new $xmldb_field_class('exitpage');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'entryoptions');
            $dbman->add_field($table, $field);
            $DB->set_field('quizport_units', 'exitpage', 1);
        }

        $field = new $xmldb_field_class('exittext');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'exitpage');
            $dbman->add_field($table, $field);
        }

        $field = new $xmldb_field_class('exitoptions');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'exittext');
            $dbman->add_field($table, $field);
            $DB->set_field('quizport_units', 'exitoptions', 0x7F); // =127 i.e. everything
        }

        $field = new $xmldb_field_class('nextactivity');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'exitoptions');
            $dbman->add_field($table, $field);
            $DB->set_field('quizport_units', 'nextactivity', -4); // -4 i.e. next quizport in this section
        }

        // update default and notnull values on "grade" fields
        $field = new $xmldb_field_class('grademethod');
        if ($dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1');
            $dbman->change_field_type($table, $field);
        }

        $field = new $xmldb_field_class('gradelimit');
        if ($dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '100');
            $dbman->change_field_type($table, $field);
        }

        $field = new $xmldb_field_class('gradeweighting');
        if ($dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '100');
            $dbman->change_field_type($table, $field);
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033122;
    if ($result && $oldversion < $newversion) {
        $files = array(
            'attempt_pagelib.php',
            'editcolumnlists_pagelib.php', 'editcolumnlists_form.php',
            'editcondition_pagelib.php', 'editcondition_form.php',
            'editquiz_pagelib.php', 'editquiz_form.php',
            'editquizzes_pagelib.php', 'editunits_pagelib.php',
            'index_pagelib.php', 'lib_forms.php',
            'lib_local.php', 'pagelib.php',
            'report_pagelib.php', 'view_pagelib.php'
        );
        foreach ($files as $file) {
            @unlink($CFG->dirroot.'/mod/quizport/'.$file);
        }
        unset($files, $file);
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033123;
    if ($result && $oldversion < $newversion) {
        // this upgrade was to clear the strings in Moodle <= 1.6
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033124;
    if ($result && $oldversion < $newversion) {
        // adjust fields in  "quizport_units" table
        $table = new $xmldb_table_class('quizport_units');

        $field = new $xmldb_field_class('nextactivity');
        if ($dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'exitoptions');
            $dbman->rename_field($table, $field, 'exitcm');
        }

        $field = new $xmldb_field_class('entrycm');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'parentid');
            $dbman->add_field($table, $field);
        }

        $field = new $xmldb_field_class('entrygrade');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '100', 'entrycm');
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033125;
    if ($result && $oldversion < $newversion) {
        $DB->set_field_select('quizport_quizzes', 'outputformat', '', "outputformat='0'");
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033128;
    if ($result && $oldversion < $newversion) {
        // drop field: quizport_cache.quizportversion
        $table = new $xmldb_table_class('quizport_cache');
        $field = new $xmldb_field_class('quizportversion');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033129;
    if ($result && $oldversion < $newversion) {
        // add new "title" option to entry/exit page options
        $DB->execute('UPDATE {quizport_units} SET entryoptions=(2*entryoptions)+1, exitoptions=(2*exitoptions)+1');
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033130;
    if ($result && $oldversion < $newversion) {

        // add "title" field to quizzes and cache
        $tablenames = array('quizport_quizzes', 'quizport_cache');
        foreach ($tablenames as $tablename) {
            // convert old navigation values
            $DB->execute('UPDATE {'.$tablename.'} SET navigation=0 WHERE navigation=6'); // none
            $DB->execute('UPDATE {'.$tablename.'} SET navigation=8 WHERE navigation=5'); // give up
            // add new title field
            $table = new $xmldb_table_class($tablename);
            $field = new $xmldb_field_class('title');
            if (! $dbman->field_exists($table, $field)) {
                xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '3', 'navigation');
                $dbman->add_field($table, $field);
                // although the default value is "3" (=use quiz name)
                // original code behaved like "0" (=get from file)
                // e.g. see fix_title() in output/hp/class.php
                $DB->execute('UPDATE {'.$tablename.'} SET title=0');
            }
        }

        // add "name" field to cache, and transfer quiz names
        $table = new $xmldb_table_class('quizport_cache');
        $field = new $xmldb_field_class('name');

        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'quizport_enableswf');
            $dbman->add_field($table, $field);
            if ($DB->get_dbfamily()=='mysql') {
                $DB->execute('UPDATE {quizport_cache} qc, {quizport_quizzes} qq SET qc.name=qq.name WHERE qc.quizid=qq.id');
            } else {
                $DB->execute('UPDATE {quizport_cache} SET name=qq.name FROM {quizport_quizzes} qq WHERE quizid=qq.id');
            }
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033132;
    if ($result && $oldversion < $newversion) {
        $DB->execute("UPDATE {quizport_quizzes} SET outputformat='html_xhtml' WHERE outputformat='html'");
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033138;
    if ($result && $oldversion < $newversion) {

        // add "stopbutton" and "stoptext" fields to QuizPort's quizzes and cache tables
        $tablenames = array('quizport_quizzes', 'quizport_cache');
        foreach ($tablenames as $tablename) {
            $table = new $xmldb_table_class($tablename);

            // add "stopbutton" field
            $field = new $xmldb_field_class('stopbutton');
            if (! $dbman->field_exists($table, $field)) {
                xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'title');
                $dbman->add_field($table, $field);
            }

            // add "stoptext" field
            $field = new $xmldb_field_class('stoptext');
            if (! $dbman->field_exists($table, $field)) {
                xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'stopbutton');
                $dbman->add_field($table, $field);
            }

            // transfer "Give Up" settings from "navigation" to "stopbutton" and "stoptext"
            $fields = "stopbutton=1, stoptext='quizport_giveup', navigation=navigation-8";
            $DB->execute('UPDATE {'.$tablename.'} SET '.$fields.' WHERE navigation>7');
        }

        // show Moodle header, navigation and footer on all popup windows
        $fields = "popupoptions=".$DB->sql_concat('popupoptions', "',MOODLEHEADER,MOODLEFOOTER,MOODLENAVBAR'");
        $DB->execute('UPDATE {quizport_units}'." SET $fields WHERE showpopup=1 AND NOT popupoptions=''");

        $fields = "popupoptions='MOODLEHEADER,MOODLEFOOTER,MOODLENAVBAR'";
        $DB->execute('UPDATE {quizport_units}'." SET $fields WHERE showpopup=1 AND popupoptions=''");

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033139;
    if ($result && $oldversion < $newversion) {
        $DB->execute('UPDATE {quizport_conditions} SET conditionquizid=-12 WHERE conditionquizid=-10'); // QUIZPORT_CONDITIONQUIZID_MENUALL
        $DB->execute('UPDATE {quizport_conditions} SET conditionquizid=-10 WHERE conditionquizid=-11'); // QUIZPORT_CONDITIONQUIZID_MENUNEXT
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033142;
    if ($result && $oldversion < $newversion) {
        // add "clickreporting" field to QuizPort's cache table
        $table = new $xmldb_table_class('quizport_cache');
        $field = new $xmldb_field_class('clickreporting');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'delay3');
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033146;
    if ($result && $oldversion < $newversion) {
        notify(get_string('fixinggrades', 'quizport', get_string('thismaytakeawhile', 'quizport')), 'notifysuccess');
        quizport_fix_grades();
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033147;
    if ($result && $oldversion < $newversion) {

        // make sure field exists: quizport_units.attemptgrademethod
        $table = new $xmldb_table_class('quizport_units');
        $field = new $xmldb_field_class('attemptgrademethod');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'attemptlimit');
            $dbman->add_field($table, $field);
        }

        // make sure field does NOT exist: quizport_quizzes.scorepriority
        $table = new $xmldb_table_class('quizport_quizzes');
        $field = new $xmldb_field_class('scorepriority');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033150;
    if ($result && $oldversion < $newversion) {
        $table = new $xmldb_table_class('quizport_conditions');
        $field = new $xmldb_field_class('sortorder');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'conditionquizid');
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033151;
    if ($result && $oldversion < $newversion) {
        // remove jmemory files
        $jmatch = $CFG->dirroot.'/mod/quizport/output/hp/6/jmatch';
        @unlink($jmatch.'/xml/jmemory/templates/djmatch5.ht_');
        @unlink($jmatch.'/xml/jmemory/templates/djmatch6.ht_');
        @unlink($jmatch.'/xml/jmemory/templates/jmatch5.ht_');
        @unlink($jmatch.'/xml/jmemory/templates/jmatch6.ht_');
        @unlink($jmatch.'/xml/jmemory/class.php');
        @unlink($jmatch.'/jmemory.js');
        @rmdir($jmatch.'/xml/jmemory/templates');
        @rmdir($jmatch.'/xml/jmemory');
        //
        // Note: cache will be cleared later (see 2008033164)
        //
        // change jmemory to jmemori
        $DB->execute("UPDATE {quizport_quizzes} SET sourcetype = REPLACE(sourcetype, 'jmemory', 'jmemori')");
        $DB->execute("UPDATE {quizport_quizzes} SET outputformat = REPLACE(outputformat, 'jmemory', 'jmemori')");
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033152;
    if ($result && $oldversion < $newversion) {
        // fix Findit(a) quizzes that have been incorrectly identified as FindIt(b)
        $tables = '{quizport_quizzes} qq, {quizport_units} qu, {quizport} q';
        $fields = ''
            .'qq.id, qq.sourcefile, qq.sourcetype, qq.sourcelocation, '
            .'qu.id AS unitid, q.id AS quizportid, q.course AS courseid'
        ;
        $select = ''
            ."qq.sourcetype = 'hp_6_jcloze_html_findit_b'"
            ." AND qq.unitid = qu.id"
            ." AND qu.parenttype = 0"
            ." AND qu.parentid = q.id"
        ;
        if ($quizzes = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select")) {
            $quizids = array();
            foreach ($quizzes as $quiz) {
                switch ($quiz->sourcelocation) {
                    case 0: $courseid = $quiz->courseid; break;
                    case 1: $courseid = SITEID; break;
                    default: continue;
                }
                $filepath = $CFG->dataroot."/$courseid/".$quiz->sourcefile;
                if (is_readable($filepath)) {
                    if ($contents = file_get_contents($filepath)) {
                        if (strpos($contents, 'Find-it - Version 3.1a')) {
                            $quizids[] = $quiz->id;
                        }
                    }
                }
            }
            //
            // Note: cache will be cleared later (see 2008033164)
            //
        }
        unset($tables, $fields, $select, $quizids, $quizzes, $quiz, $filepath, $contents);
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033155;
    if ($result && $oldversion < $newversion) {

        // reset status on "abandoned" unit attempts
        // for all QuizPorts with no explicit "End of Unit"

        $debug = $DB->get_debug();
        $DB->set_debug(false);

        notify(get_string('fixinggrades', 'quizport', get_string('thismaytakeawhile', 'quizport')), 'notifysuccess');

        // get all quizports
        if ($quizports = $DB->get_records('quizport')) {
            foreach ($quizports as $quizport) {

                // get unit for this quizport
                $select = "parenttype=0 AND parentid=$quizport->id";
                if (! $unit = $DB->get_record_select('quizport_units', $select)) {
                    continue; // shouldn't happen !!
                }

                // skip this unit if any of its quizzes
                // has an explicit end of unit post-condition
                $quizids = "SELECT id FROM {quizport_quizzes} WHERE unitid=$unit->id";
                $select = "conditiontype=2 AND quizid IN ($quizids) AND nextquizid=-99"; // -99 = end of unit
                if ($DB->record_exists_select('quizport_conditions', $select)) {
                    continue; // "End of unit" post-condition exists
                }

                // get "abandoned" unit attempts
                $select = "unitid=$unit->id AND status=3"; // 3 = abandoned
                if (! $unitattempts = $DB->get_records_select('quizport_unit_attempts', $select)) {
                    continue; // there are no "abandoned" unit attempts
                }

                // count quizzes in this unit
                $countquizzes = $DB->count_records_select('quizport_quizzes', "unitid=$unit->id");

                // reset status on these unit attempts
                $userids = array();
                foreach ($unitattempts as $unitattempt) {
                    // if all quiz scores for this unit attempt are completed,
                    // set unit attempt status and unit grade status to completed (=4)
                    $select = "quizid IN ($quizids) AND unumber=$unitattempt->unumber AND userid=$unitattempt->userid AND status=4";
                    if ($DB->count_records_select('quizport_quiz_scores', $select)==$countquizzes) {
                        $DB->set_field('quizport_unit_attempts', 'status', 4, array('id'=>$unitattempt->id));
                        $DB->set_field('quizport_unit_grades', 'status', 4, array('parenttype'=>$unit->parenttype, 'parentid'=>$unit->parentid));
                        $userids[$unitattempt->userid] = true;
                    }
                }
                unset($unitattempts);

                // update Moodle gradebook for these users
                foreach (array_keys($userids) as $userid) {
                    quizport_update_grades($quizport, $userid);
                }
                unset($userids);
                unset($unit);
            }
            unset($quizports);
        }
        $DB->set_debug($debug);
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033159;
    if ($result && $oldversion < $newversion) {

        $debug = $DB->get_debug();
        notify(get_string('fixinggrades', 'quizport', get_string('thismaytakeawhile', 'quizport')), 'notifysuccess');

        // fix quiz attempts which have no duration
        $duration = 'resumefinish-resumestart';
        $select = 'status>1 AND duration=0 AND resumefinish>resumestart';
        $DB->execute('UPDATE {quizport_quiz_attempts} SET duration = '.$duration.' WHERE '.$select);

        // fix quiz scores which have no duration
        $select = 'status>1 AND duration=0';
        if ($records = $DB->get_records_select('quizport_quiz_scores', $select)) {
            $DB->set_debug(false);
            foreach ($records as $record) {
                $select = "quizid=$record->quizid AND userid=$record->userid AND unumber=$record->unumber";
                if ($duration = $DB->count_records_select('quizport_quiz_attempts', $select, null, 'SUM(duration)')) {
                    $DB->set_field('quizport_quiz_scores', 'duration', $duration, array('id'=>$record->id));
                }
            }
            unset($records, $record, $tables, $select, $duration);
            $DB->set_debug($debug);
        }

        // fix unit attempts which have no duration
        $select = 'status>1 AND duration=0';
        if ($records = $DB->get_records_select('quizport_unit_attempts', $select)) {
            $DB->set_debug(false);
            foreach ($records as $record) {
                $tables = '{quizport_quiz_scores} qqs JOIN {quizport_quizzes} qq ON qqs.quizid=qq.id';
                $select = "qq.unitid=$record->unitid AND qqs.userid=$record->userid AND qqs.unumber=$record->unumber";
                if ($duration = $DB->count_records_sql("SELECT SUM(qqs.duration) FROM $tables WHERE $select")) {
                    $DB->set_field('quizport_unit_attempts', 'duration', $duration, array('id'=>$record->id));
                }
            }
            unset($records, $record, $tables, $select, $duration);
            $DB->set_debug($debug);
        }

        // fix unit grades which have no duration
        $select = 'status>1 AND duration=0';
        if ($records = $DB->get_records_select('quizport_unit_grades', $select)) {
            $DB->set_debug(false);
            foreach ($records as $record) {
                $tables = '{quizport_unit_attempts} qua JOIN {quizport_units} qu ON qua.unitid=qu.id';
                $select = "qu.parenttype=$record->parenttype AND qu.parentid=$record->parentid AND qua.userid=$record->userid";
                if ($duration = $DB->count_records_sql("SELECT SUM(duration) FROM $tables WHERE $select")) {
                    $DB->set_field('quizport_unit_grades', 'duration', $duration, array('id'=>$record->id));
                }
            }
            unset($records, $record, $tables, $select, $duration);
            $DB->set_debug($debug);
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033165;
    if ($result && $oldversion < $newversion) {
        // convert nextquizid values in quizport_conditions table (see quizport/lib.local.php)
        $DB->execute('UPDATE {quizport_conditions} SET nextquizid=nextquizid-10 WHERE nextquizid IN (-13,-12,-11,-10)');
        $DB->execute('UPDATE {quizport_conditions} SET nextquizid=nextquizid-5 WHERE nextquizid IN (-8,-7,-6,-5)');
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033169;
    if ($result && $oldversion < $newversion) {
        @unlink($CFG->dirroot.'/mod/quizport/attempt.php');
        @unlink($CFG->dirroot.'/mod/quizport/attempt.class.php');
        $result = $result && update_capabilities('mod/quizport');
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033176;
    if ($result && $oldversion < $newversion) {
        $table = new $xmldb_table_class('quizport_quiz_attempts');
        $fieldnames = array(
            'starttime' => 'duration', 'endtime' => 'starttime', // $fieldname => $previous field
        );
        foreach ($fieldnames as $fieldname => $previous) {
            $field = new $xmldb_field_class($fieldname);
            if (! $dbman->field_exists($table, $field)) {
                xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', $previous);
                $dbman->add_field($table, $field);
            }
        }
    }

    $newversion = 2008033177;
    if ($result && $oldversion < $newversion) {
        $table = new $xmldb_table_class('quizport_units');

        $field = new $xmldb_field_class('allowfreeaccess');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'allowresume');
            $dbman->add_field($table, $field);
        }

        $field = new $xmldb_field_class('gradeignore');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'grademethod');
            $dbman->add_field($table, $field);
        }

        $table = new $xmldb_table_class('quizport_quizzes');

        $field = new $xmldb_field_class('scoreignore');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'scoremethod');
            $dbman->add_field($table, $field);
        }
    }

    $newversion = 2008033179;
    if ($result && $oldversion < $newversion) {

        $debug = $DB->get_debug();
        $DB->set_debug(false);

        $select = '*';
        $from   = '{quizport_unit_attempts} qua';
        $where  = "qua.grade=99";

        if ($count = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where")) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where");
        } else {
            $rs = false;
        }
        if ($rs) {
            $bar = new progress_bar('quizportupgradegrades', 500, true);
            $i=0;
            $strupdating = get_string('updatinggrades', 'quizport');
            $bar->update($i, $count, $strupdating.": ($i/$count)");

            $units = array();
            $quizports = array();
            $tables = '{quizport_quizzes} qq RIGHT JOIN {quizport_quiz_scores} qqs ON qqs.quizid=qq.id';

            while ($unitattempt = $next($rs)) {
                if (($i % 1000) == 0) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                }

                $select = "qq.unitid=$unitattempt->unitid AND qqs.unumber=$unitattempt->unumber AND qqs.userid=$unitattempt->userid AND qqs.score<100";
                if (! $DB->record_exists_sql("SELECT * FROM $tables WHERE $select")) {
                    // fix this unit attempt grade
                    $DB->set_field('quizport_unit_attempts', 'grade', 100, array('id' => $unitattempt->id));

                    // get unit record if necessary
                    $unitid = $unitattempt->unitid;
                    if (empty($units[$unitid])) {
                        $fields = 'id, parenttype, parentid';
                        $units[$unitid] = $DB->get_record('quizport_units', array('id' => $unitid), $fields);
                    }

                    // get quizport record if necessary
                    $quizportid = $units[$unitid]->parentid;
                    if (empty($quizports[$quizportid])) {
                        $quizports[$quizportid] = $DB->get_record('quizport', array('id' => $quizportid));
                        $quizports[$quizportid]->users = array();
                    }

                    // fix the Moodle gradebook (only once per user per quizport)
                    $userid = $unitattempt->userid;
                    if (empty($quizports[$quizportid]->users[$userid])) {
                        // fix the unit grade
                        $DB->set_field('quizport_unit_grades', 'grade', 100, array('parenttype' => $units[$unitid]->parenttype, 'parentid' => $units[$unitid]->parentid, 'userid' => $userid));

                        // fix the quizport grade
                        quizport_update_grades($quizports[$quizportid], $userid, false);
                        $quizports[$quizportid]->users[$userid] = true;
                    }
                }

                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
            $rs->$close();
        }

        // restore debug setting
        $DB->get_debug($debug);

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008033180;
    if ($result && $oldversion < $newversion) {
        $table = new $xmldb_table_class('quizport_units');

        $field = new $xmldb_field_class('allowfreeaccess');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'allowresume');
            $dbman->add_field($table, $field);
        }

        $field = new $xmldb_field_class('gradeignore');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'grademethod');
            $dbman->add_field($table, $field);
        }

        $table = new $xmldb_table_class('quizport_quizzes');

        $field = new $xmldb_field_class('scoreignore');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'scoremethod');
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    // check the grades are all in order
    $newversion = 2008033189;
    if ($result && $oldversion < $newversion) {
        if ($CFG->majorrelease>=1.9) {
            require_once($CFG->dirroot.'/mod/quizport/lib.php');

            // disable display of debugging messages
            $debug = $DB->get_debug();
            $DB->set_debug(false);

            notify(get_string('fixinggrades', 'quizport', get_string('thismaytakeawhile', 'quizport')), 'notifysuccess');

            // update grades for QuizPorts in Moodle gradebook
            quizport_update_grades();

            // restore debug setting
            $DB->get_debug($debug);
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008040120;
    if ($result && $oldversion < $newversion) {
        $unset_strings = true;
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008040121;
    if ($result && $oldversion < $newversion) {
        update_capabilities('mod/quizport');
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008040122;
    if ($result && $oldversion < $newversion) {
        $table = new $xmldb_table_class('quizport_units');
        $field = new $xmldb_field_class('exitgrade');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'exitcm');
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008040130;
    if ($result && $oldversion < $newversion) {

        // remove all orphan quizport_quiz_scores
        // created by bug in quizport_reset_userdat()

        $fields = 'qqs.id, qqs.userid, qqs.unumber, qqs.quizid';
        $tables ='{quizport_quiz_scores} qqs '
                .'LEFT JOIN {quizport_quizzes} qq ON qqs.quizid=qq.id '
                .'LEFT JOIN {quizport_unit_attempts} qua ON qqs.userid=qua.userid '
                                                       .'AND qq.unitid=qua.unitid '
                                                       .'AND qqs.unumber=qua.unumber';
        $select = ' qua.id IS NULL';

		// we get the orphan records 500 at a time
		// in order to reduce the load on computer memory
		// and minimize the effect of execution timeouts

		$count = 0;
		$limitfrom = 0;
		$limitnum = 500;

		// disable debugging
		$debug = $DB->get_debug();
		$DB->set_debug(false);

		$print = false;
        while ($records = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select", null, $limitfrom, $limitnum)) {

			if ($print==false) {
				print '<ul>'."\n";
				$print = true;
			}

			$a = (($count * $limitnum) + 1).' - '.(($count * $limitnum) + count($records));
			print '<li>'.get_string('deleteorphanrecords', 'quizport', $a).'</li>'."\n";
			$count++;

            foreach ($records as $record) {

                // delete records from quizport_quiz_attempts table
                $DB->delete_records('quizport_quiz_attempts', array('quizid'=>$record->quizid, 'userid'=>$record->userid, 'unumber'=>$record->unumber));

                // delete records from quizport_quiz_score
                $DB->delete_records('quizport_quiz_scores', array('id'=>$record->id));

                // Note: quizport_responses and quizport_details were correctly removed by quizport_reset_userdata()
            }
        }
		if ($print) {
			print '</ul>'."\n";
		}

		// re-enable debugging
		$DB->set_debug($debug);

        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008040141;
    if ($result && $oldversion < $newversion) {
        $fields = array('entrycm', 'exitcm');
        foreach ($fields as $field) {
            $DB->set_field('quizport_units', $field, -5, array($field => -3));
            $DB->set_field('quizport_units', $field, -6, array($field => -4));
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008040148;
    if ($result && $oldversion < $newversion) {

        // display all bodystyles
        set_config('quizport_bodystyles', '1,2,4,8');

        $table = new $xmldb_table_class('quizport_cache');
        $field = new $xmldb_field_class('quizport_bodystyles');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_CHAR, '8', null, XMLDB_NOTNULL, null, null, 'slasharguments');
            $dbman->add_field($table, $field);
        }

        $table = new $xmldb_table_class('quizport_cache');
        $field = new $xmldb_field_class('allowpaste');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'stoptext');
            $dbman->add_field($table, $field);
        }

        $table = new $xmldb_table_class('quizport_quizzes');
        $field = new $xmldb_field_class('allowpaste');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'stoptext');
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    $newversion = 2008040188;
    if ($result && $oldversion < $newversion) {
        $empty_cache = true;
        upgrade_mod_savepoint($result, "$newversion", 'quizport');
    }

    if ($unset_strings) {
        for ($i=10; $i<=19; $i++) {
            $config = 'quizport_missingstrings_mdl_'.$i;
            if (isset($CFG->$config)) {
                unset_config($config);
            }
        }
    }

    // unset (=clear) the cache, if necessary
    if ($empty_cache) {
        $DB->delete_records('quizport_cache');
    }

    // Moodle <= 1.9
    if (empty($module)) {
        return $result;
    }

    // Moodle <= 2.5 without TaskChain
    if (isset($module->version)) {
        if ($module->version <= 2010000000) {
            return $result;
        }
    }

    // Moodle >= 2.6 without TaskChain
    if (isset($module->pluginversion)) {
        if ($module->pluginversion <= 2010000000) {
            return $result;
        }
    }

    //===== 2.0 upgrade line ======//

    $newversion = 2014050158;
    if ($result && $oldversion < $newversion) {

        ///////////////////////////////////////
        /// Convert QuizPorts to TaskChains ///
        ///////////////////////////////////////

        // get repository class (Moodle >= 2.3)
        if (file_exists($CFG->dirroot.'/repository')) {
            require_once($CFG->dirroot.'/repository/lib.php');
        }

        // add DB fields to store "new" ids
        // i.e. ids of corresponding TaskChain record
        $newfields = array(
            'quizport' => array('taskchainid'),
            'quizport_units' => array('chainid'),
            'quizport_quizzes' => array('taskid'),
            'quizport_strings' => array('newid'),
            'quizport_questions' => array('newid'),
            'quizport_responses' => array('newid'),
            'quizport_quiz_attempts' => array('newid'),
        );
        foreach ($newfields as $tablename => $fieldnames) {
            $table = new $xmldb_table_class($tablename);
            foreach ($fieldnames as $fieldname) {
                $field = new $xmldb_field_class($fieldname);
                if (! $dbman->field_exists($table, $field)) {
                    xmldb_quizport_field_set_attributes($field, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
                    $dbman->add_field($table, $field);
                }
                $index = new xmldb_index($tablename.'_'.$fieldname.'_key', XMLDB_INDEX_NOTUNIQUE, array($fieldname));
                if (! $dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }
        }

        ///////////////////////////////////////////////////
        // convert QuizPort (units) to TaskChain (chains)
        ///////////////////////////////////////////////////

        // store ids of modified courses
        $courseids = array();

        $quizportmoduleid = $DB->get_field('modules', 'id', array('name' => 'quizport'));
        $taskchainmoduleid = $DB->get_field('modules', 'id', array('name' => 'taskchain'));

        $select = 'qu.*, q.id AS quizportid, q.course, q.name, q.timecreated, q.timemodified';
        $from   = '{quizport_units} qu JOIN {quizport} q ON qu.parentid = q.id';
        $where  = 'qu.parenttype = 0 AND q.taskchainid = 0';

        if ($count = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where")) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where");
        } else {
            $rs = false;
        }
        if ($rs) {
            $bar = new progress_bar('convertunits', 500, true);
            $i=0;
            $strupdating = get_string('convertingunits', 'quizport');
            $bar->update($i, $count, $strupdating.": ($i/$count)");

            $taskchain_columns = $DB->get_columns('taskchain');
            $chain_columns = $DB->get_columns('taskchain_chains');

            foreach ($rs as $unit) {
                if (($i % 1000) == 0) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                }
                $unit = (object)$unit;
                if ($taskchain = xmldb_quizport_convert_record('taskchain', $taskchain_columns, $unit)) {
                    $DB->set_field('quizport', 'taskchainid', $taskchain->id, array('id' => $unit->quizportid));

                    // update course modules record
                    $params = array('module' => $quizportmoduleid, 'instance' => $unit->quizportid);
                    if ($cm = $DB->get_record('course_modules', $params)) {
                        $cm->module = $taskchainmoduleid;
                        $cm->instance = $taskchain->id;
                        $DB->update_record('course_modules', $cm);
                    }

                    // update records in "grade_items" and "grade_items_history"
                    $tables = array('grade_items', 'grade_items_history');
                    foreach ($tables as $table) {
                        $params = array('itemmodule' => 'quizport', 'iteminstance' => $unit->quizportid);
                        if ($grade_items = $DB->get_records($table, $params)) {
                            foreach ($grade_items as $grade_item) {
                                $grade_item->itemmodule = 'taskchain';
                                $grade_item->iteminstance = $taskchain->id;
                                $DB->update_record($table, $grade_item);
                            }
                        }
                    }
                    unset($grade_items, $grade_item, $tables, $table);

                    // mark this course as having been modified
                    $courseids[$taskchain->course] = true;

                    if ($chain = xmldb_quizport_convert_record('taskchain_chains', $chain_columns, $unit, array('parentid' => $taskchain->id))) {
                        $DB->set_field('quizport_units', 'chainid', $chain->id, array('id' => $unit->id));
                    }
                }
                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
            $rs->close();
        }

        ///////////////////////////////////////////////////
        // convert QuizPort quizzes to TaskChain tasks
        ///////////////////////////////////////////////////

        $select = 'qq.*, qu.chainid, q.id AS quizportid, q.taskchainid, q.course AS courseid, m.id AS cmid';
        $from   = '{quizport_quizzes} qq '.
                  'JOIN {quizport_units} qu ON qq.unitid = qu.id '.
                  'JOIN {quizport} q ON qu.parentid = q.id '.
                  'JOIN {course_modules} m ON q.taskchainid = m.instance AND m.module = '.$taskchainmoduleid;
        $where  = 'qq.taskid = 0 AND qu.chainid > 0 AND qu.parenttype = 0';
        $order  = 'q.course, qq.unitid, qq.sortorder';

        if ($count = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where")) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where ORDER BY $order");
        } else {
            $rs = false;
        }
        if ($rs) {
            $bar = new progress_bar('convertquizzes', 500, true);
            $i=0;
            $strupdating = get_string('convertingquizzes', 'quizport');
            $bar->update($i, $count, $strupdating.": ($i/$count)");

            // cache info on columns in "taskchain_tasks" table
            $task_columns = $DB->get_columns('taskchain_tasks');

            // get file storage object
            $fs = get_file_storage();

            // initialize site/course/module context
            if (class_exists('context_course')) {
                $sitecontext = context_course::instance(SITEID);
            } else {
                $sitecontext = get_context_instance(CONTEXT_COURSE, SITEID);
            }
            $coursecontext = (object)array('id' => 0, 'instanceid' => 0);
            $modulecontext = (object)array('id' => 0, 'instanceid' => 0);

            // loop through quizzes
            foreach ($rs as $quiz) {
                if (($i % 1000) == 0) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                }
                $quiz = (object)$quiz;

                if ($quiz->usemediafilter=='quizport') {
                    $quiz->usemediafilter = 'taskchain';
                }

                if ($task = xmldb_quizport_convert_record('taskchain_tasks', $task_columns, $quiz)) {
                    $DB->set_field('quizport_quizzes', 'taskid', $task->id, array('id' => $quiz->id));

                    // get course context for this $quiz/$task
                    if ($coursecontext->instanceid != $quiz->courseid) {
                        if (class_exists('context_course')) {
                            $coursecontext = context_course::instance($quiz->courseid);
                        } else {
                            $coursecontext = get_context_instance(CONTEXT_COURSE, $quiz->courseid);
                        }
                    }

                    // get module context for this $quiz/$task
                    if ($modulecontext->instanceid != $quiz->cmid) {
                        if (class_exists('context_module')) {
                            $modulecontext = context_module::instance($quiz->cmid);
                        } else {
                            $modulecontext = get_context_instance(CONTEXT_MODULE, $quiz->cmid);
                        }
                    }

                    // migrate source/config file to Moodle 2.x file storage
                    $types = array('source', 'config');
                    foreach ($types as $type) {

                        $filearea = $type.'file';
                        $location = $type.'location';

                        if (empty($task->$filearea)) {
                            continue;
                        }

                        if (preg_match('/^https?:\/\//i', $task->$filearea)) {
                            $url = $task->$filearea;
                            $path = parse_url($url, PHP_URL_PATH);
                        } else {
                            $url = '';
                            $path = $task->$filearea;
                        }
                        $path = clean_param($path, PARAM_PATH);

                        // this information should be enough to access the file
                        // if it has been migrated into Moodle 2.0 file system
                        $old_filename = basename($path);
                        $old_filepath = dirname($path);
                        if ($old_filepath=='.' || $old_filepath=='') {
                            $old_filepath = '/';
                        } else {
                            $old_filepath = '/'.ltrim($old_filepath, '/'); // require leading slash
                            $old_filepath = rtrim($old_filepath, '/').'/'; // require trailing slash
                        }

                        // sanity check on $old_filename
                        if (strpos($old_filename, '.')===false) {
                            $task->$filearea = '';
                            $DB->set_field('taskchain_tasks', $filearea, $task->$filearea, array('id' => $task->id));
                            continue;
                        }

                        // update $task->$filearea, if necessary
                        if ($task->$filearea != $old_filepath.$old_filename) {
                            $task->$filearea = $old_filepath.$old_filename;
                            $DB->set_field('taskchain_tasks', $filearea, $task->$filearea, array('id' => $task->id));
                        }

                        // set $courseid and $contextid from $task->$location
                        // of where we expect to find the $file
                        //   0 : HOTPOT_LOCATION_COURSEFILES
                        //   1 : HOTPOT_LOCATION_SITEFILES
                        //   2 : HOTPOT_LOCATION_WWW (not used)
                        if ($task->$location) {
                            $courseid = SITEID;
                            $contextid = $sitecontext->id;
                        } else {
                            $courseid = $quiz->courseid;
                            $contextid = $coursecontext->id;
                        }

                        // we expect to need the $filehash to get a file that has been migrated
                        $filehash = sha1('/'.$contextid.'/course/legacy/0'.$old_filepath.$old_filename);

                        // we might also need the old file path, if the file has not been migrated
                        $oldfilepath = $CFG->dataroot.'/'.$courseid.$old_filepath.$old_filename;

                        // set parameters used to add file to filearea
                        // (sortorder=1 siginifies the "mainfile" in this filearea)
                        $file_record = array(
                            'contextid'=>$modulecontext->id, 'component'=>'mod_taskchain', 'filearea'=>$filearea,
                            'sortorder'=>1, 'itemid'=>0, 'filepath'=>$old_filepath, 'filename'=>$old_filename
                        );

                        if ($file = $fs->get_file($modulecontext->id, 'mod_taskchain', $filearea, 0, $old_filepath, $old_filename)) {
                            // file already exists for this context - shouldn't happen !!
                            // maybe an earlier upgrade failed for some reason ?
                            // anyway we must do this check, so that create_file_from_xxx() does not abort
                        } else if ($url) {
                            // file is on an external url
                            $file = $fs->create_file_from_url($file_record, $url);
                        } else if ($file = xmldb_quizport_locate_externalfile($modulecontext->id, 'mod_taskchain', $filearea, 0, $old_filepath, $old_filename)) {
                            // file exists in external repository - great !
                        } else if ($file = $fs->get_file_by_hash($filehash)) {
                            // $file has already been migrated to Moodle's file system
                            // this is the route we expect most people to come :-)
                            $file = $fs->create_file_from_storedfile($file_record, $file);
                        } else if (file_exists($oldfilepath)) {
                            // $file still exists on server's filesystem - unusual ?!
                            $file = $fs->create_file_from_pathname($file_record, $oldfilepath);
                        } else {
                            // file was not migrated and is not on server's filesystem
                            $file = false;
                        }

                        // if $file did not exist, notify user of the problem
                        if (empty($file)) {
                            if ($url) {
                                $msg = "course_modules.id=$quiz->cmid, url=$url";
                            } else {
                                $msg = "course_modules.id=$quiz->cmid, path=$path";
                            }
                            $params = array('update'=>$quiz->cmid, 'onclick'=>'this.target="_blank"');
                            $msg = html_writer::link(new moodle_url('/course/modedit.php', $params), $msg);
                            $msg = get_string($filearea.'notfound', 'taskchain', $msg);
                            echo html_writer::tag('div', $msg, array('class'=>'notifyproblem'));
                        }
                    }
                }
                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
            $rs->$close();
            unset($fs);
        }

        ///////////////////////////////////////////////////
        // transfer conditions from QuizPort to TaskChain
        ///////////////////////////////////////////////////

        $select = 'qc.*, qq.taskid';
        $from   = '{quizport_conditions} qc '.
                  'JOIN {quizport_quizzes} qq ON qc.quizid = qq.id';
        $where  = 'qq.taskid > 0';
        $order  = 'qq.unitid, qc.quizid, qc.conditiontype, qc.sortorder';

        if ($count = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where")) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where ORDER BY $order");
        } else {
            $rs = false;
        }
        if ($rs) {
            $bar = new progress_bar('convertconditions', 500, true);
            $i=0;
            $strupdating = get_string('convertingconditions', 'quizport');
            $bar->update($i, $count, $strupdating.": ($i/$count)");

            // fields to convert taskid to taskid
            $fields = array('condition', 'next');

            foreach ($rs as $condition) {
                if (($i % 1000) == 0) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                }
                $condition = (object)$condition;

                foreach ($fields as $field) {
                    $quizid = $field.'quizid';
                    $taskid = $field.'taskid';
                    if ($condition->$quizid < 0) {
                        $condition->$taskid = $condition->$quizid;
                    } else {
                        $params = array('id' => $condition->$quizid);
                        $condition->$taskid = $DB->get_field('quizport_quizzes', 'taskid', $params);
                    }
                    unset($condition->$quizid);
                }

                $id = $condition->id;
                unset($condition->id);
                unset($condition->quizid);
                if ($condition->id = $DB->insert_record('taskchain_conditions', $condition)) {
                    $DB->delete_records('quizport_conditions', array('id' => $id));
                }

                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
            $rs->$close();
        }

        ///////////////////////////////////////////////////
        // transfer strings from QuizPort to TaskChain
        ///////////////////////////////////////////////////

        $select = '*';
        $from   = '{quizport_strings}';
        $where  = 'newid = 0';

        if ($count = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where")) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where");
        } else {
            $rs = false;
        }
        if ($rs) {
            $bar = new progress_bar('convertstrings', 500, true);
            $i=0;
            $strupdating = get_string('convertingstrings', 'quizport');
            $bar->update($i, $count, $strupdating.": ($i/$count)");

            foreach ($rs as $string) {
                if (($i % 1000) == 0) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                }
                $string = (object)$string;

                $id = $string->id;
                unset($string->id);

                $params = array('md5key' => $string->md5key);
                if ($string->id = $DB->get_field('taskchain_strings', 'id', $params)) {
                    // do nothing - string already exists in TaskChain
                } else {
                    $string->id = $DB->insert_record('taskchain_strings', $string);
                }
                if ($string->id) {
                    $DB->set_field('quizport_strings', 'newid', $string->id, array('id' => $id));
                }

                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
            $rs->$close();
        }

        ///////////////////////////////////////////////////
        // transfer questions from QuizPort to TaskChain
        ///////////////////////////////////////////////////

        $select = 'CASE WHEN (qtn.text IS NULL) THEN 0 ELSE qs.id END';
        $select = "qtn.id, qz.taskid, qtn.name, qtn.md5key, qtn.type, ($select) AS txt";
        $from   = '{quizport_questions} qtn '.
                  'JOIN {quizport_quizzes} qz ON qtn.quizid = qz.id '.
                  'LEFT JOIN {quizport_strings} qs ON qtn.text = qs.id';
        $where  = 'qtn.newid = 0 AND qz.taskid > 0';
        $order  = 'qtn.quizid, qtn.id';

        if ($count = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where")) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where ORDER BY $order");
        } else {
            $rs = false;
        }
        if ($rs) {
            $bar = new progress_bar('convertquestions', 500, true);
            $i=0;
            $strupdating = get_string('convertingquestions', 'quizport');
            $bar->update($i, $count, $strupdating.": ($i/$count)");

            foreach ($rs as $question) {
                if (($i % 1000) == 0) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                }
                $question = (object)$question;

                $id = $question->id;
                unset($question->id);

                if (isset($question->txt)) {
                    $question->text = $question->txt;
                    unset($question->txt);
                } else {
                    $question->text = 0;

                }

                $params = array('taskid' => $question->taskid, 'md5key' => $question->md5key);
                if ($question->id = $DB->get_field('taskchain_questions', 'id', $params)) {
                    // do nothing - question already exists in TaskChain
                } else {
                    $question->id = $DB->insert_record('taskchain_questions', $question);
                }
                if ($question->id) {
                    $DB->set_field('quizport_questions', 'newid', $question->id, array('id' => $id));
                }

                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
            $rs->$close();
        }

        ///////////////////////////////////////////////////
        // convert QuizPort attempts to TaskChain attempts
        ///////////////////////////////////////////////////

        $select = "CASE WHEN (qd.details IS NULL) THEN '' ELSE qd.details END";
        $select = 'qqa.*, '.
                  'qq.taskid, qu.id AS unitid, qu.chainid, q.id AS quizportid, q.taskchainid, '.
                  'qqs.id AS qqs_id, qqs.score AS qqs_score, qqs.status AS qqs_status, qqs.duration AS qqs_duration, qqs.timemodified AS qqs_timemodified, '.
                  'qua.id AS qua_id, qua.grade AS qua_grade, qua.status AS qua_status, qua.duration AS qua_duration, qua.timemodified AS qua_timemodified, '.
                  'qug.id AS qug_id, qug.grade AS qug_grade, qug.status AS qug_status, qug.duration AS qug_duration, qug.timemodified AS qug_timemodified, '.
                  "($select) AS details";
        $from   = '{quizport_quiz_attempts} qqa '.
                  'JOIN {quizport_quizzes} qq ON qqa.quizid = qq.id '.
                  'JOIN {quizport_quiz_scores} qqs ON qq.id = qqs.quizid AND qqa.userid = qqs.userid AND qqa.unumber = qqs.unumber '.
                  'JOIN {quizport_units} qu ON qq.unitid = qu.id '.
                  'JOIN {quizport_unit_attempts} qua ON qu.id = qua.unitid AND qqs.userid = qua.userid AND qqs.unumber = qua.unumber '.
                  'JOIN {quizport} q ON qu.parenttype = 0 AND qu.parentid = q.id '.
                  'JOIN {quizport_unit_grades} qug ON q.id = qug.parentid AND qug.parenttype = 0 AND qua.userid = qug.userid '.
                  'LEFT JOIN {quizport_details} qd ON qqa.id = qd.attemptid';
        $where  = 'qqa.newid = 0 AND qq.taskid > 0 AND qu.chainid > 0 AND q.taskchainid > 0';
        $order  = 'q.course, q.id, qu.id, qq.sortorder, qqa.unumber, qqa.qnumber, qqa.timestart';

        if ($count = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where")) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where ORDER BY $order");
        } else {
            $rs = false;
        }
        if ($rs) {
            $bar = new progress_bar('convertattempts', 500, true);
            $i=0;
            $strupdating = get_string('convertingattempts', 'quizport');
            $bar->update($i, $count, $strupdating.": ($i/$count)");

            $quizportid    = null;
            $unitid        = null;
            $quizid        = null;

            $quizscoreid   = null;
            $unitattemptid = null;
            $unitgradeid   = null;

            foreach ($rs as $attempt) {
                if (($i % 1000) == 0) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                }
                $attempt = (object)$attempt;

                if ($quizid && $quizid != $attempt->quizid) {
                    if ($unitid && $unitid != $attempt->unitid) {
                        if ($quizportid && $quizportid != $attempt->quizportid) {
                            $params = array('parenttype' => 0, 'parentid' => $quizportid);
                            $DB->delete_records('quizport_unit_grades', $params);
                            $quizportid = $attempt->quizportid;
                        }
                        $params = array('unitid' => $unitid);
                        $DB->delete_records('quizport_unit_attempts', $params);
                        $unitid = $attempt->unitid;
                    }
                    $params = array('quizid' => $quizid);
                    $DB->delete_records('quizport_task_scores',   $params);
                    $quizid = $attempt->quizid;
                }

                if ($quizscoreid===null || $quizscoreid != $attempt->qqs_id) {
                    if ($unitattemptid===null || $unitattemptid != $attempt->qua_id) {
                        if ($unitgradeid===null || $unitgradeid != $attempt->qug_id) {

                            // add chain grade
                            $record = (object)array(
                                'parenttype' => 0, // PARENTTYPE_ACTIVITY
                                'parentid' => $attempt->taskchainid,
                                'userid'   => $attempt->userid,
                                'grade'    => $attempt->qug_grade,
                                'status'   => $attempt->qug_status,
                                'duration' => $attempt->qug_duration,
                                'timemodified' => $attempt->qug_timemodified
                            );

                            $params = array('userid' => $record->userid, 'parenttype' => $record->parenttype, 'parentid' => $record->parentid);
                            if ($record->id = $DB->get_field('taskchain_chain_grades', 'id', $params)) {
                                // shouldn't happen !!
                            } else {
                                unset($record->id);
                                $DB->insert_record('taskchain_chain_grades', $record);
                            }
                            $unitgradeid = $attempt->qug_id;
                        }

                        // add chain attempt
                        $record = (object)array(
                            'chainid'  => $attempt->chainid,
                            'userid'   => $attempt->userid,
                            'cnumber'  => $attempt->unumber,
                            'grade'    => $attempt->qua_grade,
                            'status'   => $attempt->qua_status,
                            'duration' => $attempt->qua_duration,
                            'timemodified' => $attempt->qua_timemodified
                        );

                        $params = array('userid' => $record->userid, 'chainid' => $record->chainid, 'cnumber' => $record->cnumber);
                        if ($record->id = $DB->get_field('taskchain_chain_attempts', 'id', $params)) {
                            // shouldn't happen !!
                        } else {
                            unset($record->id);
                            $record->id = $DB->insert_record('taskchain_chain_attempts', $record);
                        }
                        $unitattemptid = $attempt->qua_id;
                    }

                    // add task score
                    $record = (object)array(
                        'taskid'  => $attempt->taskid,
                        'userid'  => $attempt->userid,
                        'cnumber' => $attempt->unumber,
                        'score'   => $attempt->qqs_score,
                        'status'  => $attempt->qqs_status,
                        'duration' => $attempt->qqs_duration,
                        'timemodified' => $attempt->qqs_timemodified
                    );

                    $params = array('userid' => $record->userid, 'taskid' => $record->taskid, 'cnumber' => $record->cnumber);
                    if ($record->id = $DB->get_field('taskchain_task_scores', 'id', $params)) {
                        // shouldn't happen !!
                    } else {
                        unset($record->id);
                        $record->id = $DB->insert_record('taskchain_task_scores', $record);
                    }
                    $quizscoreid = $attempt->qqs_id;
                }

                // add task attempt
                $record = (object)array(
                    'taskid'  => $attempt->taskid,
                    'userid'  => $attempt->userid,
                    'cnumber' => $attempt->unumber,
                    'tnumber' => $attempt->qnumber,
                    'score'   => $attempt->score,
                    'status'  => $attempt->status,
                    'penalties' => $attempt->penalties,
                    'duration'  => $attempt->duration,
                    'starttime' => $attempt->starttime,
                    'endtime'   => $attempt->endtime,
                    'resumestart'  => $attempt->resumestart,
                    'resumefinish' => $attempt->resumefinish,
                    'timestart'    => $attempt->timestart,
                    'timefinish'   => $attempt->timefinish,
                    'clickreportid' => $attempt->clickreportid,
                );

                if ($record->id = $DB->insert_record('taskchain_task_attempts', $record)) {
                    $DB->set_field('quizport_quiz_attempts', 'newid', $record->id, array('id' => $attempt->id));
                }

                // fix clickreportid - usually it is the same as the task attempt id
                if ($clickreportid = $attempt->clickreportid) {
                    if ($clickreportid==$attempt->id) {
                        $clickreportid = $record->id;
                    } else {
                        $params = array('id' => $clickreportid);
                        $clickreportid = $DB->get_field('quizport_quiz_attempts', 'newid', $params);
                    }
                    if ($clickreportid) {
                        $params = array('id' => $record->id);
                        $DB->set_field('taskchain_task_attempts', 'clickreportid', $clickreportid, $params);
                    }
                }

                // transfer attempt details - not necessary on most Moodle sites
                if ($attempt->details) {
                    $record = (object)array(
                        'attemptid' => $record->id,
                        'details'   => $attempt->details,
                    );
                    if ($record->id = $DB->get_field('taskchain_details', 'id', array('attemptid' => $record->id))) {
                        // record already exists - shouldn't happen !!
                    } else {
                        $record->id = $DB->insert_record('taskchain_details', $record);
                    }
                    if ($record->id) {
                        $DB->delete_records('quizport_details', array('attemptid' => $attempt->id));
                    }
                }

                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
            $rs->$close();
        }

        ///////////////////////////////////////////////////
        // transfer responses from QuizPort to TaskChain
        ///////////////////////////////////////////////////

        $select = 'qr.id, qqa.newid AS attemptid, qq.newid AS questionid, '.
                  'qr.score, qr.weighting, qr.hints, qr.clues, qr.checks, '.
                  'qr.correct, qr.wrong, qr.ignored';
        $from   = '{quizport_responses} qr '.
                  'JOIN {quizport_questions} qq ON qr.questionid = qq.id '.
                  'JOIN {quizport_quiz_attempts} qqa ON qr.attemptid = qqa.id';
        $where  = 'qq.newid > 0 AND qqa.newid > 0';
        $order  = 'qqa.quizid, qq.id';

        if ($count = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where")) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where ORDER BY $order");
        } else {
            $rs = false;
        }
        if ($rs) {
            $bar = new progress_bar('convertresponses', 500, true);
            $i=0;
            $strupdating = get_string('convertingresponses', 'quizport');
            $bar->update($i, $count, $strupdating.": ($i/$count)");

            // these fields contain string ids
            $fields = array('correct', 'wrong', 'ignored');

            foreach ($rs as $response) {
                if (($i % 1000) == 0) {
                    upgrade_set_timeout(60 * 5); // another 5 minutes
                }
                $response = (object)$response;

                $id = $response->id;
                unset($response->id);

                foreach ($fields as $field) {
                    if ($value = $response->$field) {
                        $value = explode(',', $value);
                        $value = array_filter($value);
                        if ($value = implode(',', $value)) {
                            if ($value = $DB->get_records_select_menu('quizport_strings', "id IN ($value)", null, '', 'id,newid')) {
                                $value = array_values($value);
                                $value = array_filter($value);
                                $value = implode(',', $value);
                            }
                        }
                    }
                    $response->$field = ($value ? $value : '');
                }

                $params = array('attemptid' => $response->attemptid, 'questionid' => $response->questionid);
                if ($response->id = $DB->get_field('taskchain_responses', 'id', $params)) {
                    // do nothing - response already exists in TaskChain
                } else {
                    $response->id = $DB->insert_record('taskchain_responses', $response);
                }
                if ($response->id) {
                    $DB->delete_records('quizport_responses', array('id' => $id));
                }

                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
            $rs->$close();
        }

        // remove all quizport attempt data
        $DB->delete_records('quizport_unit_grades');
        $DB->delete_records('quizport_unit_attempts');
        $DB->delete_records('quizport_quiz_scores');
        $DB->delete_records('quizport_quiz_attempts');
        $DB->delete_records('quizport_questions');
        $DB->delete_records('quizport_responses');
        $DB->delete_records('quizport_details');
        $DB->delete_records('quizport_strings');

        // remove all quizports, units, quizzes
        $DB->delete_records('quizport_conditions');
        $DB->delete_records('quizport_quizzes');
        $DB->delete_records('quizport_cache');
        $DB->delete_records('quizport_units');
        $DB->delete_records('quizport');

        // remove extra fields
        foreach ($newfields as $tablename => $fieldnames) {
            $table = new $xmldb_table_class($tablename);
            foreach ($fieldnames as $fieldname) {
                $index = new xmldb_index($tablename.'_'.$fieldname.'_key', XMLDB_INDEX_NOTUNIQUE, array($fieldname));
                if ($dbman->index_exists($table, $index)) {
                    $dbman->drop_index($table, $index);
                }
                $field = new $xmldb_field_class($fieldname);
                if ($dbman->field_exists($table, $field)) {
                    $dbman->drop_field($table, $field);
                }
            }
        }

        upgrade_mod_savepoint($result, "$newversion", 'quizport');

        // disable quizport module
        $DB->set_field('modules', 'visible', 0, array('name' => 'quizport'));
    }

    return $result;
}

function xmldb_quizport_emptycache($empty_cache, $quizids) {
    // $quizids = ''
    //    .'SELECT id FROM {quizport_quizzes} WHERE'
    //    ." sourcetype $LIKE '%jmatch%' OR outputformat $LIKE '%jmatch%'"
    // ;
    // xmldb_quizport_emptycache($empty_cache, $quizids);
    global $DB;
    if (! $empty_cache) {
        $DB->execute("DELETE FROM {quizport_cache} WHERE quizid IN ($quizids)");
    }
}

function xmldb_quizport_field_set_attributes(&$field, $type, $precision=null, $unsigned=null, $notnull=null, $sequence=null, $default=null, $previous=null) {
    global $CFG;
    if ($CFG->majorrelease>=1.9) {
        if ($type==XMLDB_TYPE_CHAR && $notnull==XMLDB_NOTNULL && $default=='') {
            // === prevent following helpful message from lib/xmldb/xmldb_field.php ====
            // XMLDB has detected one CHAR NOT NULL column (fieldname) with '' (empty string) as DEFAULT value.
            // This type of columns must have one meaningful DEFAULT declared or none (NULL).
            // XMLDB have fixed it automatically changing it to none (NULL). The process will continue ok
            // and proper defaults will be created accordingly with each DB requirements.
            // Please fix it in source (XML and/or upgrade script) to avoid this message to be displayed.
            $default = null;
        }
    } else {
        if ($notnull==XMLDB_NOTNULL && is_null($default)) {
            // === prevent following message from lib/ddllib.php (Moodle 1.7 - 1.8) ====
            // Field xxx cannot be added.
            // Not null fields added to non empty tables require default value. Create skipped
            switch ($type) {
                case XMLDB_TYPE_INTEGER:
                case XMLDB_TYPE_NUMBER:
                case XMLDB_TYPE_FLOAT:
                case XMLDB_TYPE_DATETIME:
                case XMLDB_TYPE_TIMESTAMP:
                    $default = 0;
                    break;
                case XMLDB_TYPE_CHAR:
                case XMLDB_TYPE_TEXT:
                case XMLDB_TYPE_BINARY:
                default:
                    $default = '';
            }
        }
    }
    if (method_exists($field, 'set_attributes')) {
        // Moodle >= 2.0
        return $field->set_attributes($type, $precision, $unsigned, $notnull, $sequence, $default, $previous);
    } else {
        // Moodle 1.7 - 1.9: method name and parameter count are different
        // the two superfluous parameters are: $enum=null, $enumvalues=null
        return $field->setAttributes($type, $precision, $unsigned, $notnull, $sequence, null, null, $default, $previous);
    }
}

function xmldb_quizport_convert_record($table, $columns, $oldrecord, $values=array()) {
    global $DB;
    $newrecord = new stdClass();
    foreach ($columns as $column) {
        $name = $column->name;
        if ($name=='id') {
            // do nothing
        } else if (array_key_exists($name, $values)) {
            $newrecord->$name = $values[$name];
        } else if (isset($oldrecord->$name)) {
            $newrecord->$name = $oldrecord->$name;
        } else if (isset($column->default_value)) {
            $newrecord->$name = $column->default_value;
        } else {
            if (isset($column->meta_type)) {
                $is_num = preg_match('/[INTD]/', $column->meta_type); // Moodle >= 2.0
            } else {
                $is_num = preg_match('/int|decimal|double|float|time|year/i', $column->type);
            }
            if ($is_num) {
                $newrecord->$name = 0;
            } else {
                $newrecord->$name = '';
            }
        }
    }
    if ($newrecord->id = $DB->insert_record($table, $newrecord)) {
        return $newrecord;
    } else {
        return null;
    }
}

function xmldb_quizport_locate_externalfile($contextid, $component, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB;

    if (! class_exists('repository')) {
        return false; // Moodle <= 2.2 has no repositories
    }

    static $repositories = null;
    if ($repositories===null) {
        $exclude_types = array('recent', 'upload', 'user', 'areafiles');
        $repositories = repository::get_instances();
        foreach (array_keys($repositories) as $id) {
            if (method_exists($repositories[$id], 'get_typename')) {
                $type = $repositories[$id]->get_typename();
            } else {
                $type = $repositories[$id]->options['type'];
            }
            if (in_array($type, $exclude_types)) {
                unset($repositories[$id]);
            }
        }
        // ensure upgraderunning is set
        if (empty($CFG->upgraderunning)) {
            $CFG->upgraderunning = null;
        }
    }

    // get file storage
    $fs = get_file_storage();

    // the following types repository use encoded params
    $encoded_types = array('user', 'areafiles', 'coursefiles');

    foreach ($repositories as $id => $repository) {

        // "filesystem" path is in plain text, others are encoded
        if (method_exists($repositories[$id], 'get_typename')) {
            $type = $repositories[$id]->get_typename();
        } else {
            $type = $repositories[$id]->options['type'];
        }
        $encodepath = in_array($type, $encoded_types);

        // save $root_path, because it may get messed up by
        // $repository->get_listing($path), if $path is non-existant
        if (method_exists($repository, 'get_rootpath')) {
            $root_path = $repository->get_rootpath();
        } else if (isset($repository->root_path)) {
            $root_path = $repository->root_path;
        } else {
            $root_path = false;
        }

        // get repository type
        switch (true) {
            case isset($repository->options['type']):
                $type = $repository->options['type'];
                break;
            case isset($repository->instance->typeid):
                $type = repository::get_type_by_id($repository->instance->typeid);
                $type = $type->get_typename();
                break;
            default:
                $type = ''; // shouldn't happen !!
        }

        $path = $filepath;
        $source = trim($filepath.$filename, '/');

        // setup $params for path encoding, if necessary
        $params = array();
        if ($encodepath) {
            $listing = $repository->get_listing();
            switch (true) {
                case isset($listing['list'][0]['source']): $param = 'source'; break; // file
                case isset($listing['list'][0]['path']):   $param = 'path';   break; // dir
                default: return false; // shouldn't happen !!
            }
            $params = file_storage::unpack_reference($listing['list'][0][$param], true);

            $params['filepath'] = '/'.$path.($path=='' ? '' : '/');
            $params['filename'] = '.'; // "." signifies a directory
            $path = file_storage::pack_reference($params);
        }

        // set $nodepathmode for filesystem repository on Moodle >= 3.1
        $nodepathmode = '';
        if ($type=='filesystem') {
            if (method_exists($repository, 'build_node_path')) {
                $nodepathmode = 'browse';
                // the following code mimics the protected method
                // $repository->build_node_path($nodepathmode, $path)
                $path = $nodepathmode.':'.base64_encode($path).':';
            }
        }

        // reset $repository->root_path (filesystem repository only)
        if ($root_path) {
            $repository->root_path = $root_path;
        }

        // unset upgraderunning because it can cause get_listing() to fail
        $upgraderunning = $CFG->upgraderunning;
        $CFG->upgraderunning = null;

        // Note: we use "@" to suppress warnings in case $path does not exist
        $listing = @$repository->get_listing($path);

        // restore upgraderunning flag
        $CFG->upgraderunning = $upgraderunning;

        // check each file to see if it is the one we want
        foreach ($listing['list'] as $file) {

            switch (true) {
                case isset($file['source']): $param = 'source'; break; // file
                case isset($file['path']):   $param = 'path';   break; // dir
                default: continue; // shouldn't happen !!
            }

            if ($encodepath) {
                $file[$param] = file_storage::unpack_reference($file[$param]);
                $file[$param] = trim($file[$param]['filepath'], '/').'/'.$file[$param]['filename'];
            }

            if ($file[$param]==$source) {

                if ($encodepath) {
                    $params['filename'] = $filename;
                    $source = file_storage::pack_reference($params);
                }

                $file_record = array(
                    'contextid' => $contextid, 'component' => $component, 'filearea' => $filearea,
                    'sortorder' => 0, 'itemid' => 0, 'filepath' => $filepath, 'filename' => $filename
                );

                if ($file = $fs->create_file_from_reference($file_record, $id, $source)) {
                    return $file;
                }

                break; // try another repository
            }
        }
    }

    // external file not found (or found but not created)
    return false;
}

function xmldb_quizport_check_indexes($result, $filepath='') {
    // based on "admin/xmldb/actions/check_indexes/check_indexes.class.php" (Moodle 1.9)

    global $CFG, $DB;

    // load all XMLDB classes and generators
    require_once($CFG->dirroot.'/lib/ddllib.php');

    $dbman = $DB->get_manager();

    if ($CFG->majorrelease<=1.9) {
        $xmldb_file_class = 'XMLDBFile';
        $xmldb_index_class = 'XMLDBIndex';
        $generator_class = 'XMLDB'.$DB->get_dbfamily();
        $generator = new $generator_class();
        $generator->setPrefix($CFG->prefix);
    } else {
        // Moodle 2.0 and later
        $xmldb_file_class = 'xmldb_file';
        $xmldb_index_class = 'xmldb_index';
        $generator_class = $DB->get_dbfamily().'_sql_generator';
        $generator = new $generator_class($DB);
    }

    if ($filepath=='') {
        $filepath = $CFG->dirroot.'/mod/quizport/db/install.xml';
    }
    $xmldb_file = new $xmldb_file_class($filepath);

    $loaded = $xmldb_file->loadXMLStructure();
    if (! $loaded || !$xmldb_file->isLoaded()) {
        notify('Errors found in XMLDB file: '.$filepath);
        return false;
    }

    // Load the appropriate XMLDB generator

    // array of missing keys and indexes
    $missing_indexes = array();

    // loop through tables
    $structure = $xmldb_file->getStructure();
    if ($xmldb_tables = $structure->getTables()) {
        foreach ($xmldb_tables as $xmldb_table) {

            if (! $dbman->table_exists($xmldb_table)) {
                continue; // table doesn't exist !!
            }

            // loop through keys
            if ($xmldb_keys = $xmldb_table->getKeys()) {
                foreach ($xmldb_keys as $xmldb_key) {

                    if ($xmldb_key->getType() == XMLDB_KEY_PRIMARY) {
                        continue; // skip primary index
                    }

                    // check this key exists
                    if (! $generator->getKeySQL($xmldb_table, $xmldb_key) || $xmldb_key->getType() == XMLDB_KEY_FOREIGN) {
                        $xmldb_index = new $xmldb_index_class('anyname');
                        $xmldb_index->setFields($xmldb_key->getFields());
                        switch ($xmldb_key->getType()) {
                            case XMLDB_KEY_UNIQUE:
                            case XMLDB_KEY_FOREIGN_UNIQUE:
                                $xmldb_index->setUnique(true);
                                break;
                            case XMLDB_KEY_FOREIGN:
                                $xmldb_index->setUnique(false);
                                break;
                        }
                        if (! $dbman->index_exists($xmldb_table, $xmldb_index)) {
                            /// add the missing index to the list
                            $missing_indexes[] = (object)array('table'=>$xmldb_table, 'index'=>$xmldb_index);
                        }
                    }
                }
            } // end if keys

            // loop through indexes
            if ($xmldb_indexes = $xmldb_table->getIndexes()) {
                foreach ($xmldb_indexes as $xmldb_index) {
                    if (! $dbman->index_exists($xmldb_table, $xmldb_index)) {
                        // add the missing index to the list
                        $missing_indexes[] = (object)array('table'=>$xmldb_table, 'index'=>$xmldb_index);
                    }
                }
            } // end if indexes
        }
    }

    foreach ($missing_indexes as $obj) {
        $result = $result && add_index($obj->table, $obj->index);
    }

    return $result;
}

function quizport_fix_grades($print=true, $usequizportname=1) {
    // if quizport name and grade are different ...
    //     $usequizportname=0: set quizport name equal to grade name
    //     $usequizportname=1: set grade name equal to quizport name
    global $CFG, $DB;

    if ($CFG->majorrelease<1.9) {
        return; // no grade book
    }
    require_once($CFG->dirroot.'/lib/gradelib.php');

    // save and disable SQL debug messages
    $debug = $DB->get_debug();
    $DB->set_debug(false);

    if (! $module = $DB->get_record('modules', array('name'=>'quizport'))) {
        if ($print) {
            print_error('error_noquizport', 'quizport');
        } else {
            debugging(get_string('error_noquizport', 'quizport'), DEBUG_DEVELOPER);
        }
    }

    if (! $quizports = $DB->get_records('quizport')) {
        $quizports = array();
    }

    if(! $gradeitems = $DB->get_records_select('grade_items', "itemtype='mod' AND itemmodule='quizport'")) {
        $gradeitems = array();
    }

    $success = '<font color="green">OK</font>'."\n";
    $failure = '<font color="red">'.get_string('error').'</font>'."\n";
    $not = '<font color="red">NOT</font>'."\n";
    $new = get_string('newvalue', 'quizport');
    $old = get_string('oldvalue', 'quizport');

    $quizports_no_grade = array(); // quizports without a grade item
    $quizports_no_weighting = array(); // quizports with zero grade limit/weighting
    $gradeitems_wrong_name = array(); // grade items that have a different name from their quizport
    $gradeitems_no_quizport = array(); // grade items without a quizport
    $gradeitems_no_idnumber = array(); // grade items without an idnumber (= course_modules id)

    foreach (array_keys($gradeitems) as $id) {
        $quizportid = $gradeitems[$id]->iteminstance;
        if (array_key_exists($quizportid, $quizports)) {
            $quizports[$quizportid]->gradeitem = &$gradeitems[$id];
            if (empty($gradeitems[$id]->idnumber)) {
                $gradeitems_no_idnumber[$id] = &$gradeitems[$id];
            }
            if ($gradeitems[$id]->itemname != $quizports[$quizportid]->name) {
                $gradeitems_wrong_name[$id] = &$gradeitems[$id];
            }
        } else {
            $gradeitems_no_quizport[$id] = &$gradeitems[$id];
        }
    }

    foreach (array_keys($quizports) as $id) {
        if (empty($quizports[$id]->gradeitem)) {
            $quizports_no_grade[$id] =&$quizports[$id];
        }
    }

    if ($ids = implode(',', array_keys($quizports_no_grade))) {
        if ($units = $DB->get_records_select('quizport_units', "parenttype=0 AND parentid IN ($ids)")) {
            foreach ($units as $unit) {
                if ($unit->gradelimit==0 || $unit->gradeweighting==0) {
                    // no grade item required, because grade is always 0
                    // transfer this quizport to "no_weighting" array
                    unset($quizports_no_grade[$unit->parentid]);
                    $quizports_no_weighting[$unit->parentid] = &$quizports[$unit->parentid];
                } else {
                    $quizports[$unit->parentid]->unit = &$units[$unit->id];
                }
            }
        }
    }

    $output = '';
    $start_list = false;
    $count_idnumber_updated = 0;
    $count_idnumber_notupdated = 0;
    foreach ($gradeitems_no_idnumber as $id=>$gradeitem) {
        $idnumber = $DB->get_field('course_modules', 'idnumber', array('module'=>$module->id, 'instance'=>$gradeitem->iteminstance));
        if (! $idnumber) {
            unset($gradeitems_no_idnumber[$id]);
            continue;
        }
        if (! $start_list) {
            $start_list = true;
            if ($print) {
                print '<ul>'."\n";
            }
        }
        if ($print) {
            $a = 'grade_item(id='.$id.').idnumber: '.$new.'='.$idnumber;
            print '<li>'.get_string('updatinga', '', $a).' ... ';
        }
        if ($DB->set_field('grade_items', 'idnumber', addslashes($idnumber), array('id'=>$id))) {
            $count_idnumber_updated++;
            if ($print) {
                print $success;
            }
        } else {
            $count_idnumber_notupdated++;
            if ($print) {
                print $failure;
            }
        }
        if ($print) {
            print '</li>'."\n";
        }
    }
    if ($start_list) {
        if ($print) {
            print '</ul>'."\n";
        }
    }

    $start_list = false;
    $count_name_updated = 0;
    $count_name_notupdated = 0;
    foreach ($gradeitems_wrong_name as $id=>$gradeitem) {
        $gradename = $gradeitem->itemname;
        $quizportid = $gradeitem->iteminstance;
        $quizportname = $quizports[$quizportid]->name;
        if (! $start_list) {
            $start_list = true;
            if ($print) {
                print '<ul>'."\n";
            }
        }
        if ($usequizportname) {
            if ($print) {
                $a = 'grade_item(id='.$id.').name: '.$old.'='.$gradename.' '.$new.'='.$quizportname;
                print '<li>'.get_string('updatinga', '', $a).' ... ';
            }
            $set_field = $DB->set_field('grade_items', 'itemname', addslashes($quizportname), array('id'=>$id));
        } else {
            if ($print) {
                $a = 'quizport(id='.$quizportid.').name: '.$old.'='.$quizportname.' '.$new.'='.$gradename;
                print '<li>'.get_string('updatinga', '', $a).' ... ';
            }
            $set_field = $DB->set_field('quizport', 'name', addslashes($gradename), array('id'=>$quizportid));
        }
        if ($set_field) {
            $count_name_updated++;
            if ($print) {
                print $success;
            }
        } else {
            $count_name_notupdated++;
            if ($print) {
                print $failure;
            }
        }
        if ($print) {
            print '</li>'."\n";
        }
    }
    if ($start_list) {
        if ($print) {
            print '</ul>'."\n";
        }
    }

    $start_list = false;
    $count_deleted = 0;
    $count_notdeleted = 0;
    if ($ids = implode(',', array_keys($gradeitems_no_quizport))) {
        $count = count($gradeitems_no_quizport);
        if (! $start_list) {
            $start_list = true;
            if ($print) {
                print '<ul>'."\n";
            }
        }
        if ($print) {
            print '<li>deleting '.$count.' grade items with no quizports ... ';
        }
        if ($DB->delete_records_select('grade_items', "id in ($ids)")) {
            $count_deleted = $count;
            if ($print) {
                print $success;
            }
        } else {
            $count_notdeleted = $count;
            if ($print) {
                print $failure;
            }
        }
        if ($print) {
            print '</li>'."\n";
        }
    }
    if ($start_list) {
        if ($print) {
            print '</ul>'."\n";
        }
    }

    $start_list = false;
    $count_added = 0;
    $count_notadded = 0;
    foreach ($quizports_no_grade as $quizportid=>$quizport) {
        $params = array(
            'itemname' => $quizport->name
        );
        if ($coursemoduleid = $DB->get_field('course_modules', 'id', array('module'=>$module->id, 'instance'=>$quizportid))) {
            $params['idnumber'] = $coursemoduleid;
        }
        if (isset($quizports[$quizportid]->unit)) {
            $unit = &$quizports[$quizportid]->unit;
            $params['gradetype'] = GRADE_TYPE_VALUE;
            $params['grademax']  = $unit->gradelimit * ($unit->gradeweighting/100);
            $params['grademin']  = 0;
        } else {
            $params['gradetype'] = GRADE_TYPE_NONE; // no grade item needed
        }
        if (! $start_list) {
            $start_list = true;
            if ($print) {
                print '<ul>'."\n";
            }
        }
        if ($print) {
            print '<li>adding grade item for quizport (id='.$quizport->id.' name='.$quizport->name.') ... ';
        }
        if (grade_update('mod/quizport', $quizport->course, 'mod', 'quizport', $quizportid, 0, null, $params)==GRADE_UPDATE_OK) {
            $count_added++;
            if ($print) {
                print $success;
            }
        } else {
            $count_notadded++;
            if ($print) {
                print $failure;
            }
        }
        if ($print) {
            print '</li>'."\n";
        }
    }
    if ($start_list) {
        if ($print) {
            print '</ul>'."\n";
        }
    }

    if ($print) {
        print "<ul>\n";
        print "  <li>".count($quizports)." QuizPorts were found</li>\n";
        if ($count = count($quizports_no_weighting)) {
            print "  <li>$count quizport(s) have zero grade limit/weighting</li>\n";
        }
        print "  <li>".count($gradeitems)." grade items were found</li>\n";
        if ($count = count($gradeitems_no_idnumber)) {
            if ($count_idnumber_updated) {
                print "  <li>$count_idnumber_updated / $count grade item idnumber(s) were successfully updated</li>\n";
            }
            if ($count_idnumber_notupdated) {
                print "  <li>$count_idnumber_notupdated / $count grade item idnumber(s) could $not be updated !!</li>\n";
            }
        }
        if ($count = count($gradeitems_wrong_name)) {
            if ($count_name_updated) {
                print "  <li>$count_name_updated / $count grade item name(s) were successfully updated</li>\n";
            }
            if ($count_name_notupdated) {
                print "  <li>$count_name_notupdated / $count grade item name(s) could $not be updated !!</li>\n";
            }
        }
        if ($count = count($gradeitems_no_quizport)) {
            if ($count_deleted) {
                print "  <li>$count_deleted / $count grade item(s) were successfully deleted</li>\n";
            }
            if ($count_notdeleted) {
                print "  <li>$count_notdeleted / $count grade item(s) could $not be deleted !!</li>\n";
            }
        }
        if ($count = count($quizports_no_grade)) {
            if ($count_added) {
                print "  <li>$count_added / $count grade item(s) were successfully added</li>\n";
            }
            if ($count_notadded) {
                print "  <li>$count_notadded / $count grade item(s) could $not be added !!</li>\n";
            }
        }
        print "</ul>\n";
    }
    $DB->set_debug($debug);
}
?>
