<?php // $Id$
/**
 * Standardize Moodle API for Moodle 1.0
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// set missing strings for Moodle 1.0
if (empty($CFG->quizport_missingstrings_mdl_10)) {
    $missingstrings['mdl_10'] = array(
        'en_utf8' => array(
            'moodle' => array(
                'go' => 'Go',
                'select' => 'Select',
                'next' => 'Next'
            ),
            'quiz' => array(
                'reportoverview' => 'Overview',
                'reportsimplestat' => 'Simple statistics'
            ),
            'resource' => array(
                'newresizable' => 'Allow the window to be resized',
                'newscrollbars' => 'Allow the window to be scrolled',
                'newdirectories' => 'Show the directory links',
                'newlocation' => 'Show the location bar',
                'newmenubar' => 'Show the menu bar',
                'newtoolbar' => 'Show the toolbar',
                'newstatus' => 'Show the status bar',
                'newwidth' => 'Default window width (in pixels)',
                'newheight' => 'Default window height (in pixels)'
            )
        )
    );
}
?>