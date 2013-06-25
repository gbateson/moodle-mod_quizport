<?php
function quizport_upgrade($oldversion, $module=null, $stopversion=0) {
    global $CFG, $db;
    $result = true;

    if (floatval($CFG->release)>=1.7) {
        // Moodle 1.7 and later should use upgrade.php
        return true;
    }

    if ($CFG->dbtype=='mysql') {
        $LIKE = 'LIKE';
    } else {
        $LIKE = 'ILIKE';
    }

    // these switches will be set to "true" if necessary
    $empty_cache = false;
    $unset_strings = false;

    $newversion = 2008033101;
    if ($result && $oldversion < $newversion) {
        quizport_check_indexes();
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033105;
    if ($result && $oldversion < $newversion) {
        table_column('quizport_cache', '', 'sourcelastmodified', 'varchar', 255, '', '', 'not null', 'sourcelocation');
        table_column('quizport_cache', '', 'sourceetag', 'varchar', 255, '', '', 'not null', 'sourcelastmodified');
        table_column('quizport_cache', '', 'configlastmodified', 'varchar', 255, '', '', 'not null', 'configlocation');
        table_column('quizport_cache', '', 'configetag', 'varchar', 255, '', '', 'not null', 'configlastmodified');
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033106;
    if ($result && $oldversion < $newversion) {
        $options = array(
            'options', 'resizable', 'scrollbars', 'directories', 'location',
            'menubar', 'toolbar', 'status', 'width', 'height'
        );
        foreach ($options as $option) {
            quizport_unset_config("quizport_popup$option");
        }
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033107;
    if ($result && $oldversion < $newversion) {
        quizport_set_config('quizport_maxeventlength', '5');
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033108;
    if ($result && $oldversion < $newversion) {
        table_column('quizport_conditions', 'attempttime', 'attemptduration', 'integer', 10, '', 0, 'not null');
        table_column('quizport_conditions', '', 'attemptdelay', 'integer', 10, '', 0, 'not null', 'attemptduration');
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033112;
    if ($result && $oldversion < $newversion) {
        modify_database("", "INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'editcolumnlists', 'quizport', 'name');");
        modify_database("", "INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'editcondition', 'quizport', 'name');");
        modify_database("", "INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'editquiz', 'quizport', 'name');");
        modify_database("", "INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'editquizzes', 'quizport', 'name');");
        modify_database("", "INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'report', 'quizport', 'name');");
        modify_database("", "INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'submit', 'quizport', 'name');");
        modify_database("", "INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'view', 'quizport', 'name');");
        execute_sql("DELETE FROM {$CFG->prefix}log WHERE course=0 AND module='quizport'");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033113;
    if ($result && $oldversion < $newversion) {
        execute_sql("UPDATE {$CFG->prefix}quizport_conditions SET nextquizid=quizid WHERE conditiontype=1");
        execute_sql("UPDATE {$CFG->prefix}quizport_conditions SET conditionquizid=quizid WHERE conditiontype=2");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033114;
    if ($result && $oldversion < $newversion) {
        quizport_check_indexes();
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033117;
    if ($result && $oldversion < $newversion) {
        table_column('quizport_units', '', 'attemptgrademethod', 'integer', 4, '', 0, 'not null', 'attemptlimit');
        execute_sql("UPDATE {$CFG->prefix}quizport_units SET attemptgrademethod=1 WHERE id IN (SELECT DISTINCT unitid FROM {$CFG->prefix}quizport_quizzes WHERE scorepriority=1)");
        execute_sql("ALTER TABLE {$CFG->prefix}quizport_quizzes DROP scorepriority;");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033120;
    if ($result && $oldversion < $newversion) {
        // adjust fields in  "quizport_units" table

        // rename old "intro" fields to "entry" field
        table_column('quizport_units', 'showintro', 'entrypage', 'integer', 2, 'unsigned', 0, 'not null');
        table_column('quizport_units', 'intro', 'entrytext', 'text', '', '', '', 'not null');

        // add new fields for "entry" and "exit" pages
        table_column('quizport_units', '', 'entryoptions', 'integer', 10, 'unsigned', 0, 'not null', 'entrytext');
        table_column('quizport_units', '', 'exitpage', 'integer', 2, 'unsigned', 0, 'not null', 'entryoptions');
        table_column('quizport_units', '', 'exittext', 'text', '', '', '', 'not null', 'exitpage');
        table_column('quizport_units', '', 'exitoptions', 'integer', 10, 'unsigned', 0, 'not null', 'exittext');
        table_column('quizport_units', '', 'nextactivity', 'integer', 10, '', 0, 'not null', 'exitoptions');

        // set defaults on new fields
        execute_sql("UPDATE {$CFG->prefix}quizport_units SET entryoptions=15, exitpage=1, exitoptions=127, nextactivity=-4");

        // set default values on grade fields
        table_column('quizport_units', 'grademethod', 'grademethod', 'integer', 4, 'unsigned', 1, 'not null');
        table_column('quizport_units', 'gradelimit', 'gradelimit', 'integer', 6, 'unsigned', 100, 'not null');
        table_column('quizport_units', 'gradeweighting', 'gradeweighting', 'integer', 6, '', 100, 'not null');

        quizport_upgrade_savepoint($newversion);
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
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033124;
    if ($result && $oldversion < $newversion) {
        // adjust fields in  "quizport_units" table

        // create "entrycm" and "entrygrade"
        table_column('quizport_units', '', 'entrycm', 'integer', 10, '', 0, 'not null', 'parentid');
        table_column('quizport_units', '', 'entrygrade', 'integer', 6, 'unsigned', 100, 'not null', 'entrycm');

        // rename "nextactivity" to "exitcm"
        table_column('quizport_units', 'nextactivity', 'exitcm', 'integer', 10, '', 0, 'not null', 'exitoptions');

        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033125;
    if ($result && $oldversion < $newversion) {
        execute_sql("UPDATE {$CFG->prefix}quizport_quizzes SET outputformat='' WHERE outputformat='0'");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033128;
    if ($result && $oldversion < $newversion) {
        execute_sql("ALTER TABLE {$CFG->prefix}quizport_cache DROP quizportversion;");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033129;
    if ($result && $oldversion < $newversion) {
        execute_sql("UPDATE {$CFG->prefix}quizport_units SET entryoptions=(2*entryoptions)+1, exitoptions=(2*exitoptions)+1");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033130;
    if ($result && $oldversion < $newversion) {

        // add "title" field to quizzes and cache and fix "navigation" field
        $tables = array('quizport_quizzes', 'quizport_cache');
        foreach ($tables as $table) {
            // add "title" field
            table_column($table, '', 'title', 'integer', 6, 'unsigned', 3, 'not null', 'navigation');
            // although the default value is "3" (=use quiz name)
            // original code behaved like "0" (=get from file)
            // e.g. see fix_title() in output/hp/class.php
            execute_sql("UPDATE {$CFG->prefix}$table SET title=0");
            // fix navigation field
            execute_sql("UPDATE {$CFG->prefix}$table SET navigation=0 WHERE navigation=6"); // none
            execute_sql("UPDATE {$CFG->prefix}$table SET navigation=8 WHERE navigation=5"); // give up
        }

        // add "name" field to cache, and transfer quiz names
        table_column('quizport_cache', '', 'name', 'varchar', 255, '', '', 'not null', 'quizport_enableswf');
        quizport_upgrade_copy_values('quizport_quizzes', 'name', 'id', 'quizport_cache', 'name', 'quizid');

        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033132;
    if ($result && $oldversion < $newversion) {
        execute_sql("UPDATE {$CFG->prefix}quizport_quizzes SET outputformat='html_xhtml' WHERE outputformat='html'");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033138;
    if ($result && $oldversion < $newversion) {
        // add "stopbutton" and "stoptext" fields to QuizPort's quizzes and cache tables
        $tables = array('quizport_quizzes', 'quizport_cache');
        foreach ($tables as $table) {
            table_column($table, '', 'stopbutton', 'integer', 2, 'unsigned', 0, 'not null', 'title');
            table_column($table, '', 'stoptext', 'varchar', 255, '', '', 'not null', 'stopbutton');
            // transfer "Give Up" settings from "navigation" to "stopbutton" and "stoptext"
            $fields = "stopbutton=1, stoptext='quizport_giveup', navigation=navigation-8";
            execute_sql("UPDATE {$CFG->prefix}$table SET $fields WHERE navigation>7");
        }

        // show Moodle header, navigation and footer on all popup windows
        $fields = 'popupoptions='.quizport_upgrade_concat('popupoptions', "',MOODLEHEADER,MOODLEFOOTER,MOODLENAVBAR'");
        execute_sql("UPDATE {$CFG->prefix}quizport_units SET $fields WHERE showpopup=1 AND NOT popupoptions=''");

        $fields = "popupoptions='MOODLEHEADER,MOODLEFOOTER,MOODLENAVBAR'";
        execute_sql("UPDATE {$CFG->prefix}quizport_units SET $fields WHERE showpopup=1 AND popupoptions=''");

        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033139;
    if ($result && $oldversion < $newversion) {
        execute_sql("UPDATE {$CFG->prefix}quizport_conditions SET conditionquizid=-12 WHERE conditionquizid=-10"); // QUIZPORT_CONDITIONQUIZID_MENUALL
        execute_sql("UPDATE {$CFG->prefix}quizport_conditions SET conditionquizid=-10 WHERE conditionquizid=-11"); // QUIZPORT_CONDITIONQUIZID_MENUNEXT
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033142;
    if ($result && $oldversion < $newversion) {
        table_column('quizport_cache', '', 'clickreporting', 'integer', 2, 'unsigned', 0, 'not null', 'delay3');
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033150;
    if ($result && $oldversion < $newversion) {
        table_column('quizport_conditions', '', 'sortorder', 'integer', 4, 'unsigned', 0, 'not null', 'conditionquizid');
        quizport_upgrade_savepoint($newversion);
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
        // Note: cache will be cleared below (see 2008033164)
        //
        // change jmemory to jmemori
        execute_sql("UPDATE {$CFG->prefix}quizport_quizzes SET sourcetype = REPLACE(sourcetype, 'jmemory', 'jmemori')");
        execute_sql("UPDATE {$CFG->prefix}quizport_quizzes SET outputformat = REPLACE(outputformat, 'jmemory', 'jmemori')");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033152;
    if ($result && $oldversion < $newversion) {
        // fix Findit(a) quizzes that have been incorrectly identified as FindIt(b)
        $tables = "{$CFG->prefix}quizport_quizzes qq, {$CFG->prefix}quizport_units qu, {$CFG->prefix}quizport q";
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
        if ($quizzes = get_records_sql("SELECT $fields FROM $tables WHERE $select")) {
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
            // cache will be cleared below (see 2008033164)
            //
        }
        unset($tables, $fields, $select, $quizids, $quizzes, $quiz, $filepath, $contents);
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033155;
    if ($result && $oldversion < $newversion) {
        // reset status on "abandoned" unit attempts
        // for all QuizPorts with no explicit "End of Unit"

        // switch off messages to the screen
        $debug = $db->debug;
        $db->debug = false;

        // get quizports
        if ($quizports = get_records('quizport')) {
            foreach ($quizports as $quizport) {

                // get unit for this quizport
                $select = "parenttype=0 AND parentid=$quizport->id";
                if (! $unit = get_record_select('quizport_units', $select)) {
                    continue; // shouldn't happen !!
                }

                // skip this unit if any of its quizzes
                // has an explicit end of unit post-condition
                $quizids = "SELECT id FROM {$CFG->prefix}quizport_quizzes WHERE unitid=$unit->id";
                $select = "conditiontype=2 AND quizid IN ($quizids) AND nextquizid=-99"; // -99 = end of unit
                if (record_exists_sql("SELECT * FROM {$CFG->prefix}quizport_conditions WHERE $select LIMIT 1")) {
                    continue; // "End of unit" post-condition exists
                }

                // get "abandoned" unit attempts
                $select = "unitid=$unit->id AND status=3"; // 3 = abandoned
                if (! $unitattempts = get_records_select('quizport_unit_attempts', $select)) {
                    continue; // there are no "abandoned" unit attempts
                }

                // count quizzes in this unit
                $countquizzes = count_records_select('quizport_quizzes', "unitid=$unit->id");

                // reset status on these unit attempts
                foreach ($unitattempts as $unitattempt) {
                    // if all quiz scores for this unit attempt are completed,
                    // set unit attempt status and unit grade status to completed (=4)
                    $select = "quizid IN ($quizids) AND unumber=$unitattempt->unumber AND userid=$unitattempt->userid AND status=4";
                    if (count_records_select('quizport_quiz_scores', $select)==$countquizzes) {
                        set_field('quizport_unit_attempts', 'status', 4, 'id', $unitattempt->id);
                        set_field('quizport_unit_grades', 'status', 4, 'parenttype', $unit->parenttype, 'parentid', $unit->parentid);
                    }
                }
                unset($unitattempts);
                unset($unit);
            }
            unset($quizports);
        }
        $db->debug = $debug;
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033159;
    if ($result && $oldversion < $newversion) {

        // fix quiz attempts which have no duration
        $duration = 'resumefinish-resumestart';
        $select = 'status>1 AND duration=0 AND resumefinish>resumestart';
        execute_sql("UPDATE {$CFG->prefix}quizport_quiz_attempts SET duration = $duration WHERE $select");

        // fix quiz scores which have no duration
        $duration = ''
            .'SELECT SUM(qqa.duration) '
            ."FROM {$CFG->prefix}quizport_quiz_attempts qqa "
            .'WHERE qqa.quizid=qqs.quizid AND qqa.userid=qqs.userid AND qqa.unumber=qqs.unumber'
        ;
        $select = 'qqs.status>1 AND qqs.duration=0';
        execute_sql("UPDATE {$CFG->prefix}quizport_quiz_scores qqs SET duration = ($duration) WHERE $select");

        // fix unit attempts which have no duration
        $duration = ''
            .'SELECT SUM(qqs.duration) '
            ."FROM {$CFG->prefix}quizport_quiz_scores qqs JOIN {$CFG->prefix}quizport_quizzes qq ON qqs.quizid=qq.id "
            .'WHERE qq.unitid=qua.unitid AND qqs.userid=qua.userid AND qqs.unumber=qua.unumber'
        ;
        $select = 'qua.status>1 AND qua.duration=0';
        execute_sql("UPDATE {$CFG->prefix}quizport_unit_attempts qua SET duration = ($duration) WHERE $select");

        // fix unit grades which have no duration
        $duration = ''
            .'SELECT SUM(qua.duration) '
            ."FROM {$CFG->prefix}quizport_unit_attempts qua JOIN {$CFG->prefix}quizport_units qu ON qua.unitid=qu.id "
            .'WHERE qu.parenttype=qug.parenttype AND qu.parentid=qug.parentid AND qua.userid=qug.userid'
        ;
        $select = 'qug.status>1 AND qug.duration=0';
        execute_sql("UPDATE {$CFG->prefix}quizport_unit_grades qug SET duration = ($duration) WHERE $select");

        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033165;
    if ($result && $oldversion < $newversion) {
        // convert nextquizid values in quizport_conditions table
        execute_sql("UPDATE {$CFG->prefix}quizport_conditions SET nextquizid=nextquizid-10 WHERE nextquizid IN (-13,-12,-11,-10)");
        execute_sql("UPDATE {$CFG->prefix}quizport_conditions SET nextquizid=nextquizid-5 WHERE nextquizid IN (-8,-7,-6,-5)");
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033169;
    if ($result && $oldversion < $newversion) {
        @unlink($CFG->dirroot.'/mod/quizport/attempt.php');
        @unlink($CFG->dirroot.'/mod/quizport/attempt.class.php');
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033170;
    if ($result && $oldversion < $newversion) {
        $unset_strings = true;
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008033176;
    if ($result && $oldversion < $newversion) {
        $table = "{$CFG->prefix}quizport_quiz_attempts";
        $fields = array(
            'starttime' => 'duration', 'endtime' => 'starttime'
        );
        foreach ($fields as $field => $previous) {
            $rs = $db->Execute("SELECT attname FROM pg_attribute WHERE attname = '$field' AND attrelid = (SELECT oid FROM pg_class WHERE relname = '$table')");
            if (empty($rs) || $rs->RecordCount()==0) {
                // field does not exist, so create it
                table_column($table, '', $field, 'integer', 10, '', 0, 'not null', $previous);
            }
        }
    }

    $newversion = 2008033177;
    if ($result && $oldversion < $newversion) {
        table_column('quizport_units', '', 'allowfreeaccess', 'integer', 6, '', 0, 'not null', 'allowresume');
        table_column('quizport_units', '', 'gradeignore', 'integer', 2, 'unsigned', 0, 'not null', 'grademethod');
        table_column('quizport_quizzes', '', 'scoreignore', 'integer', 2, 'unsigned', 0, 'not null', 'scoremethod');
    }

    $newversion = 2008040119;
    if ($result && $oldversion < $newversion) {
        $config = 'quizport_missingstrings_mdl_15';
        if (floatval($CFG->release)==1.5 && isset($CFG->$config)) {
            if (function_exists('fulldelete')) {
                @fulldelete($CFG->dataroot.'/lang/en/help/quizport');
            }
            quizport_unset_config($config);
        }
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008040120;
    if ($result && $oldversion < $newversion) {
        $unset_strings = true;
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008040122;
    if ($result && $oldversion < $newversion) {
        table_column('quizport_units', '', 'exitgrade', 'integer', 6, 'unsigned', 0, 'not null', 'exitcm');
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008040130;
    if ($result && $oldversion < $newversion) {

        // remove all orphan quizport_quiz_scores
        // created by bug in quizport_reset_userdat()

        $fields = 'qqs.id, qqs.userid, qqs.unumber, qqs.quizid';
        $tables ="{$CFG->prefix}quizport_quiz_scores qqs "
                ."LEFT JOIN {$CFG->prefix}quizport_quizzes qq ON qqs.quizid=qq.id "
                ."LEFT JOIN {$CFG->prefix}quizport_unit_attempts qua ON qqs.userid=qua.userid "
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
        $debug = $db->debug;
        $db->debug = false;

        $print = false;
        while ($records = get_records_sql("SELECT $fields FROM $tables WHERE $select", $limitfrom, $limitnum)) {

            if ($print==false) {
                print '<ul>'."\n";
                $print = true;
            }

            $a = (($count * $limitnum) + 1).' - '.(($count * $limitnum) + count($records));
            print '<li>'.get_string('deleteorphanrecords', 'quizport', $a).'</li>'."\n";
            $count++;

            foreach ($records as $record) {

                // delete records from quizport_quiz_attempts table
                delete_records('quizport_quiz_attempts', 'quizid', $record->quizid, 'userid', $record->userid, 'unumber', $record->unumber);

                // delete records from quizport_quiz_score
                delete_records('quizport_quiz_scores', 'id', $record->id);

                // Note: quizport_responses and quizport_details were correctly removed by quizport_reset_userdata()
            }
        }
        if ($print) {
            print '</ul>'."\n";
        }

        // re-enable debugging
        $db->debug = $debug;

        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008040139;
    if ($result && $oldversion < $newversion) {
        $empty_cache = true;
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008040141;
    if ($result && $oldversion < $newversion) {
        $fields = array('entrycm', 'exitcm');
        foreach ($fields as $field) {
            execute_sql("UPDATE {$CFG->prefix}quizport_units SET $field = -5 WHERE $field = -3");
            execute_sql("UPDATE {$CFG->prefix}quizport_units SET $field = -6 WHERE $field = -4");
        }
        $unset_strings = true;
        quizport_upgrade_savepoint($newversion);
    }

    $newversion = 2008040142;
    if ($result && $oldversion < $newversion) {
        $empty_cache = true;
        quizport_upgrade_savepoint($newversion);
    }

    // reset missingstrings, if necessary
    if ($unset_strings) {
        for ($i=10; $i<=19; $i++) {
            quizport_unset_config("quizport_missingstrings_mdl_$i");
        }
    }

    // clear the cache, if necessary
    if ($empty_cache) {
        execute_sql("TRUNCATE TABLE {$CFG->prefix}quizport_cache");
    }

    return $result;
}

function quizport_upgrade_emptycache($empty_cache, $quizids) {
    // $quizids = ''
    //    ."SELECT id FROM {$CFG->prefix}quizport_quizzes WHERE"
    //    ." sourcetype $LIKE '%jmix%' OR outputformat $LIKE '%jmix%'"
    // ;
    // quizport_upgrade_emptycache($empty_cache, $quizids);
    global $CFG;
    if (! $empty_cache) {
        execute_sql("DELETE FROM {$CFG->prefix}quizport_cache WHERE quizid IN ($quizids)");
    }
}

function quizport_upgrade_savepoint($version) {
    global $CFG;
    execute_sql("UPDATE {$CFG->prefix}modules SET version=$version WHERE name='quizport'");
}

function quizport_set_config($name, $value) {
    global $CFG;
    if (isset($CFG->$name)) {
        execute_sql("UPDATE {$CFG->prefix}config SET value='$value' WHERE name='$name'");
    } else {
        execute_sql("INSERT INTO {$CFG->prefix}config (name, value) VALUES ('$name', '$value')");
    }
    $CFG->$name = $value;
}

function quizport_unset_config($name) {
    global $CFG;
    if (isset($CFG->$name)) {
        unset($CFG->$name);
        execute_sql("DELETE FROM {$CFG->prefix}config WHERE name='$name'");
    }
}

function quizport_upgrade_concat() {
    global $CFG;
    $args = func_get_args();
    if ($CFG->dbtype=='mysql') {
        return 'CONCAT('.implode(',', $args).')';
    }
    if ($CFG->dbtype=='postgre7') {
        return implode('||', $args);
    }
    // MSSQL ?
    return implode('+', $args);
}

function quizport_upgrade_copy_values($table1, $field1, $key1, $table2, $field2, $key2) {
    // copy values from $table1.$field1 to $table2.$field2 WHERE $table1.$key1=$table2.$key2
    global $CFG;
    if ($CFG->dbtype=='mysql') {
        execute_sql(
            "UPDATE {$CFG->prefix}$table2 t2, {$CFG->prefix}$table1 t1 "
            ."SET t2.$field2=t1.$field1 WHERE t2.$key2=t1.$key1"
        );
    }
    if ($CFG->dbtype=='postgre7') {
        execute_sql(
            "UPDATE {$CFG->prefix}$table2"
            ."SET $field2=t1.$field1 FROM {$CFG->prefix}$table1 t1 WHERE $key2=t1.$key1"
        );
    }
    // MSSQL ?
    return '';
}

function quizport_check_indexes() {
    // check that all the indexes are uptodate
    global $CFG, $db;

    $filepath = $CFG->dirroot.'/mod/quizport/db/'.$CFG->dbtype.'.sql';
    if (! file_exists($filepath)) {
        return false; // file not found !!
    }
    if (function_exists('file_get_contents')) {
        $filecontents = file_get_contents($filepath);
    } else { // PHP < 4.3
        $filecontents = file($filepath);
        if (is_array($filecontents)) {
             $filecontents = implode('', $filecontents);
        }
    }
    if (! $filecontents) {
        return false; // file not readable !!
    }


    // save and disable debug setting
    $debug = $db->debug;
    $db->debug = false;

    // get old (=current) indexes on QuizPort tables
    $oldindexes = array();
    switch ($CFG->dbtype) {

        case 'mysql':
            // MySQL: get names of QuizPort tables
            $tables = array();
            if ($rs = $db->Execute("SHOW TABLES LIKE '".$CFG->prefix."quizport%'")) {
                while ($row = $rs->FetchRow()) {
                    $tablenames[] = array_shift($row);
                }
                $rs->close();
            }

            // MySQL: get indexes
            foreach ($tablenames as $tablename) {
                if ($rs = $db->Execute("SHOW INDEXES FROM $tablename")) {
                    while ($index = $rs->FetchNextObj()) {
                        if ($index->Key_name=='PRIMARY') {
                            continue; // don't touch primary indexes
                        }
                        $indexname = $index->Key_name;
                        if (empty($oldindexes[$indexname])) {
                            $oldindexes[$indexname] = (object)array(
                                'tablename' => $tablename,
                                'fields' => array(),
                                'is_unique' => ($index->Non_unique ? false : true)
                            );
                        }
                        $fieldindex = intval($index->Seq_in_index) - 1;
                        $oldindexes[$indexname]->fields[$fieldindex] = $index->Column_name;
                    }
                    $rs->close();
                }
            }
            break;

        case 'postgres7':

            // PostgreSQL: get indexes
            // more info about pg catalog tables - if you need it :-)
            // http://www.postgresql.org/docs/7.4/static/catalog-pg-class.html
            // http://www.postgresql.org/docs/7.4/static/catalog-pg-index.html
            // http://www.postgresql.org/docs/7.4/static/catalog-pg-attribute.html

            $fields = ''
                .'c.oid::regclass, c.relname, c.relkind,'
                .'i.indrelid, i.indexrelid, i.indisprimary, i.indisunique, '
                .'a.attrelid, a.attname, a.attnum'
            ;
            $tables = ''
                .'pg_class c '
                .'INNER JOIN pg_index i ON (c.oid = i.indexrelid) '
                .'INNER JOIN pg_attribute a ON (i.indexrelid = a.attrelid)'
            ;
            $select = ''
                ."c.relname ILIKE '".$CFG->prefix."%ix' AND c.relkind='i' "
                ."AND i.indrelid IN (SELECT oid::regclass FROM pg_class WHERE relname ILIKE 'mdl_quizport%')"
            ;

            if ($rs = $db->Execute("SELECT $fields FROM $tables WHERE $select")) {
                while ($index = $rs->FetchNextObj()) {
                    if ($index->indisprimary=='t') {
                        continue; // don't touch primary indexes
                    }
                    $indexname = $index->relname;
                    if (empty($oldindexes[$indexname])) {
                        $oldindexes[$indexname] = (object)array(
                            'fields' => array(),
                            'tablename' => '', // pg_call.relname
                            'is_unique' => ($index->indisunique=='t')
                        );
                    }
                    $fieldindex = intval($index->attnum) - 1;
                    $oldindexes[$indexname]->fields[$fieldindex] = $index->attname;
                }
                $rs->close();
            }
            break;
    }

    // restore debug setting
    $db->debug = $debug;

    // get new indexes from the sql file
    $newindexes = array();

    // first, get index definitions within table definitions
    switch ($CFG->dbtype) {
        case 'mysql':
            if (preg_match_all('/CREATE TABLE prefix_(\w+) \('.'([^;]*)'.'\)[^;)]*;/is', $filecontents, $tables)) {

                // $tables holds definitions for QuizPort tables
                //   [1][$t] : table name (without prefix)
                //   [2][$t] : table fields and indexes

                $t_max = count($tables[0]);
                for ($t=0; $t<$t_max; $t++) {
                    $tablename = $CFG->prefix.$tables[1][$t];

                    // get indexes for this table
                    if (! preg_match_all('/((?:UNIQUE KEY)|KEY)\s+'.'prefix_(\w+) \('.'([^)]*)'.'\)/', $tables[2][$t], $indexes)) {
                        continue;
                    }

                    // $indexes holds definitions for indexes on this table
                    //   [1][$i] : UNIQUE KEY or KEY
                    //   [2][$i] : index name (without prefix)
                    //   [3][$i] : index fields (comma seaprated)

                    $i_max = count($indexes[0]);
                    for ($i=0; $i<$i_max; $i++) {
                        $indexname = $CFG->prefix.$indexes[2][$i];
                        $newindexes[$indexname] = (object)array(
                            'tablename' => $tablename,
                            'is_unique' => ($indexes[1][$i]=='UNIQUE KEY'),
                            'fields' => explode(',', str_replace(' ', '', $indexes[3][$i]))
                        );
                    }
                }
            }
            break;
    }

    switch ($CFG->dbtype) {
        case 'mysql':
            $search = '/ALTER TABLE prefix_(\w+)\s+ADD\s+((?:UNIQUE INDEX)|(?:UNIQUE KEY)|INDEX|KEY)\s+'.'prefix_(\w+)\s+\('.'([^)]*)'.'\)/';
            $i_table  = 1; // table name (without prefix)
            $i_unique = 2; // UNIQUE KEY/INDEX or KEY/INDEX
            $i_index  = 3; // index name (without prefix)
            $i_fields = 4; // index fields (comma seaprated)
            break;
        case 'postgres7':
            $search = '/CREATE\s+((?:UNIQUE INDEX)|INDEX)\s+prefix_(\w+)\s+ON\s+prefix_(\w+)\s+\('.'([^)]*)'.'\)/';
            $i_unique = 1; // UNIQUE INDEX or INDEX
            $i_index  = 2; // index name (without prefix)
            $i_table  = 3; // table name (without prefix)
            $i_fields = 4; // index fields (comma seaprated)
            break;
        default:
            $search = '';
    }

    // get indexes defined separately in the sql file
    if ($search && preg_match_all($search, $filecontents, $indexes)) {
        $i_max = count($indexes[0]);
        for ($i=0; $i<$i_max; $i++) {
            $tablename = $CFG->prefix.$indexes[$i_table][$i];
            $indexname = $CFG->prefix.$indexes[$i_index][$i];
            $newindexes[$indexname] = (object)array(
                'tablename' => $tablename,
                'is_unique' => (substr($indexes[$i_unique][$i], 0, 6)=='UNIQUE'),
                'fields' => explode(',', str_replace(' ', '', $indexes[$i_fields][$i]))
            );
        }
    }

    foreach ($newindexes as $indexname=>$newindex) {
        $add = true;
        if (array_key_exists($indexname, $oldindexes)) {
            $delete = true;
            $oldindex =&$oldindexes[$indexname];
            if ($oldindex->is_unique==$newindex->is_unique) {
                if (count($newindex->fields)==count($oldindex->fields)) {
                    $diff = array_diff($newindex->fields, $oldindex->fields);
                    if (count($diff)==0) {
                        $add = false;
                        $delete = false;
                    }
                }
            }
            if ($delete) {
                switch ($CFG->dbtype) {
                    case 'mysql':
                        $db->Execute("ALTER TABLE $newindex->tablename DROP INDEX $indexname");
                        break;
                    case 'postgres7':
                        $db->Execute("DROP INDEX $indexname");
                        break;
                }
            }
            unset($oldindex);
            unset($oldindexes[$indexname]);
        }
        if ($add) {
            $fields = implode(',', $newindex->fields);
            $INDEX = ($newindex->is_unique ? 'UNIQUE INDEX' : 'INDEX');
            switch ($CFG->dbtype) {
                case 'mysql':
                    $db->Execute("ALTER TABLE $newindex->tablename ADD $INDEX $indexname ($fields)");
                    break;
                case 'postgres7':
                    $db->Execute("CREATE $INDEX $indexname ON $newindex->tablename ($fields)");
                    break;
            }
        }
    }

    // remove surplus indexes
    foreach ($oldindexes as $indexname=>$oldindex) {
        switch ($CFG->dbtype) {
            case 'mysql':
                $db->Execute("ALTER TABLE $oldindex->tablename DROP INDEX $indexname");
                break;
            case 'postgres7':
                $db->Execute("DROP INDEX $indexname");
                break;
        }
    }

    return true;
}
?>
