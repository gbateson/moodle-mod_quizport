<?php // $Id$
/**
* Standardize Moodle API for Moodle 1.5
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

if (! function_exists('get_all_instances_in_courses')) {
    // copy of print_textfield() (lib/datalib.php) Moodle 1.6
    function get_all_instances_in_courses($modulename, $courses) {
        global $CFG;

        if (! $courses || ! is_array($courses) || ! count($courses)) {
            return array();
        }
        $tables = ''
            .$CFG->prefix.'course_modules cm,'.$CFG->prefix.'course_sections cs,'
            .$CFG->prefix.'modules md,'.$CFG->prefix.$modulename.' m'
        ;
        $fields = ''
            .'cm.id AS coursemodule, m.*, cs.section, '
            .'cm.visible AS visible, cm.groupmode, cm.course'
        ;
        $select = ''
            ."cm.course IN (".implode(',', array_keys($courses)).") AND cm.instance = m.id AND "
            ."cm.section = cs.id AND md.name = '$modulename' AND md.id = cm.module"
        ;
        if (! $rawmods = get_records_sql("SELECT $fields FROM $tables WHERE $select")) {
            return array();
        }

        $instances = array();
        foreach ($courses as $course) {
            if (isteacher($course->id)) {
                $invisible = -1;
            } else {
                $invisible = 0;
            }
            if (! $modinfo = unserialize($course->modinfo)) {
                continue;
            }
            foreach ($modinfo as $mod) {
                if ($mod->mod == $modulename && $mod->visible > $invisible) {
                    $instance = $rawmods[$mod->cm];
                    if (! empty($mod->extra)) {
                        $instance->extra = $mod->extra;
                    }
                    $instances[] = $instance;
                }
            }
        }
        return $instances;
    }
}

if (! function_exists('print_textfield')) {
    // copy of print_textfield() (lib/weblib.php)
    function print_textfield($name, $value, $alt = '',$size=50,$maxlength=0, $return=false) {

        static $idcounter = 0;

        if (! $name) {
            $name = 'unnamed';
        }

        if (! $alt) {
            $alt = 'textfield';
        }

        if ($maxlength) {
            $maxlength = ' maxlength="'.$maxlength.'" ';
        }

        $htmlid = 'auto-tf'.sprintf('%04d', ++$idcounter);
        $output  = '<span class="textfield '.$name."\">";
        $output .= '<input name="'.$name.'" id="'.$htmlid.'" type="text" value="'.$value.'" size="'.$size.'" '.$maxlength.' alt="'.$alt.'" />';

        $output .= '</span>'."\n";

        if (empty($return)) {
            echo $output;
        } else {
            return $output;
        }
    }
}

if (! function_exists('choose_from_menu_nested')) {
    // copy of choose_from_menu_nested() (lib/weblib.php)
    function choose_from_menu_nested($options,$name,$selected='',$nothing='choose',$script = '',
                                     $nothingvalue=0,$return=false,$disabled=false,$tabindex=0) {

        if ($nothing == 'choose') {
            $nothing = get_string('choose').'...';
        }

        $attributes = '';
        if ($script) {
            $attributes .= ' onchange="'. $script .'"';
        }
        if ($disabled) {
            $attributes .= ' disabled="disabled"';
        }
        if ($tabindex) {
            $attributes .= ' tabindex="'.$tabindex.'"';
        }

        $output = '<select id="menu'.$name.'" name="'. $name .'" '. $attributes .'>' . "\n";
        if ($nothing) {
            $output .= '   <option value="'. $nothingvalue .'"'. "\n";
            if ($nothingvalue === $selected) {
                $output .= ' selected="selected"';
            }
            $output .= '>'. $nothing .'</option>' . "\n";
        }
        if (! empty($options)) {
            foreach ($options as $section => $values) {

                $output .= '   <optgroup label="'. s(format_string($section)) .'">'."\n";
                foreach ($values as $value => $label) {
                    $output .= '   <option value="'. format_string($value) .'"';
                    if ((string)$value == (string)$selected) {
                        $output .= ' selected="selected"';
                    }
                    if ($label === '') {
                        $output .= '>'. $value .'</option>' . "\n";
                    } else {
                        $output .= '>'. $label .'</option>' . "\n";
                    }
                }
                $output .= '   </optgroup>'."\n";
            }
        }
        $output .= '</select>' . "\n";

        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }
}

// set missing strings for Moodle 1.5
if (empty($CFG->quizport_missingstrings_mdl_15)) {
    $missingstrings['mdl_15'] = array(
        'en_utf8' => array(
            'form' => array(
                'day' => 'Day',
                'month' => 'Month',
                'year' => 'Year',
                'hour' => 'Hour',
                'minute' => 'Minute'
            ),
            'quiz' => array(
                'results' => 'Results'
            ),
            'quizport' => 'all'
        )
    );
}
?>