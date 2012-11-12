<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.2
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

if (! defined('SITEID')) {
    define('SITEID', 1);
}

if (empty($CFG->quizport_missingstrings_mdl_12)) {
    $missingstrings['mdl_12'] = array(
        'en_utf8' => array(
            'quiz' => array(
                'requirepassword' => 'Require password',
                'requiresubnet' => 'Require network address'
            )
        )
    );
}
?>