<?php

if (empty($GLOBALS['CFG'])) {
    die;
}
if (empty($CFG)) {
    global $CFG;
}
if (empty($CFG->majorrelease)) {
    $CFG->majorrelease = floatval($CFG->release);
}

//
// Capability definitions for the QuizPort module
// ==============================================
//
$mod_quizport_capabilities = array(

    // Ability to see that the QuizPort exists, and the basic information
    // on the entry page, for example the start date and time limit.
    'mod/quizport:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    // Ability to submit quiz results as a 'student'.
    'mod/quizport:attempt' => array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    // Ability for a 'Student' to review their previous attempts.
    // Review by 'Teachers' is controlled by mod/quizport:viewreports.
    'mod/quizport:reviewmyattempts' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    // Edit the quiz settings, add and remove quizzes.
    'mod/quizport:manage' => array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    // Preview the quiz.
    'mod/quizport:preview' => array(
        'captype' => 'write', // Only just a write.
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    // Manually grade (and comment on) student attempts at a quiz, and regrade quizzes.
    'mod/quizport:grade' => array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    // View the quiz reports.
    'mod/quizport:viewreports' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    // Delete attempts using the overview report.
    'mod/quizport:deleteattempts' => array(
        'riskbitmask' => (defined('RISK_DATALOSS') ? RISK_DATALOSS : RISK_PERSONAL), // Moodle 1.8
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        )
    ),

    // Do not have the time limit imposed.
    // Used for accessibility legislation compliance.
    'mod/quizport:ignoretimelimits' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array()
    )
);

if ($CFG->majorrelease >= 2.0) {
    // Moodle >= 2.0

    // rename "mod_quizport_capabilities" to "capabilities"
    $capabilities = $mod_quizport_capabilities;
    unset($mod_quizport_capabilities);

    // "addinstance" capability is required in Moodle 2.x
    // but we intentionally disable it for all roles
    $capabilities['mod/quizport:addinstance'] = array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array() // i.e. disabled
    );

    // rename all "admin" capabilities to "manager"
    foreach (array_keys($capabilities) as $name) {
        if (! array_key_exists('legacy', $capabilities[$name])) {
            continue;
        }
        if (! array_key_exists('admin', $capabilities[$name]['legacy'])) {
            continue;
        }
        $capabilities[$name]['legacy']['manager'] = $capabilities[$name]['legacy']['admin'];
        unset($capabilities[$name]['legacy']['admin']);
    }
}

if ($CFG->majorrelease <= 1.9 && empty($CFG->mod_quizport_version)) {
    // Moodle 1.7 - 1.9 install

    // we are supposed to add these records with a <STATEMENTS> block in install.xml
    // <STATEMENTS>
    //   <STATEMENT NAME="insert log_display" TYPE="insert" TABLE="log_display" COMMENT="Initial insert of records on table log_display">
    //     <SENTENCES>
    //       <SENTENCE TEXT="(module, action, mtable, field) VALUES ('quizport', 'editcolumnlists', 'quizport', 'name')" />
    //       <SENTENCE TEXT="(module, action, mtable, field) VALUES ('quizport', 'editcondition', 'quizport', 'name')" />
    //       <SENTENCE TEXT="(module, action, mtable, field) VALUES ('quizport', 'editquiz', 'quizport', 'name')" />
    //       <SENTENCE TEXT="(module, action, mtable, field) VALUES ('quizport', 'editquizzes', 'quizport', 'name')" />
    //       <SENTENCE TEXT="(module, action, mtable, field) VALUES ('quizport', 'report', 'quizport', 'name')" />
    //       <SENTENCE TEXT="(module, action, mtable, field) VALUES ('quizport', 'submit', 'quizport', 'name')" />
    //       <SENTENCE TEXT="(module, action, mtable, field) VALUES ('quizport', 'view', 'quizport', 'name')" />
    //     </SENTENCES>
    //   </STATEMENT>
    // </STATEMENTS>

    // However, the above XML generates a warning on Moodle 2.x,
    // so we do this workaround ...

    $actions = array(
        'editcolumnlists', 'editcondition', 'editquiz', 'editquizzes', 'report', 'submit', 'view'
    );
    foreach($actions as $action) {
        $record = (object)array(
            'module' => 'quizport',
            'action' => $action,
            'mtable' => 'quizport',
            'field'  => 'name'
        );
        if ($record->id = get_field('log_display', 'id', 'module', 'quizport', 'action', $action)) {
            update_record('log_display', $record);
        } else {
            insert_record('log_display', $record);
        }
    }

    // on Moodle <= 1.6 the log_display records will be installed from mysql.sql and postgres7.sql
}
?>