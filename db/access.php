<?php // $Id$
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
?>