<?php // $Id$
/**
 * add missing Moodle strings for Moodle <= 1.9
*
* @author Gordon Bateson
* @version $Revision$ : Last updated on $Date$ by $Author$
* @license http://www.gnu.org/copyleft/gpl.html GNU Public License
* @package quizport
*/

// function to add missing strings to legacy Moodle sites
function quizport_add_missingstrings($missingstrings) {
    // $missingstrings[$moodleversion][$lang][$module][$stringname] = $stringvalue
    global $CFG;

    foreach ($missingstrings as $moodleversion=>$languages) {

        $quizport_missingstrings = 'quizport_missingstrings_'.$moodleversion;
        if (! empty($CFG->$quizport_missingstrings)) {
            continue;
        }

        // set a config flag so we don't come through here again
        set_config($quizport_missingstrings, '1');

        // $basedir for lang files
        if ($CFG->majorrelease<=1.4) {
            $basedir = $CFG->dirroot.'/lang';
        } else {
            $basedir = $CFG->dataroot.'/lang';
        }
        if (! file_exists($basedir)) {
            if (! @mkdir($basedir)) {
                debugging('QuizPort module could not create lang dir: '.$basedir, DEBUG_DEVELOPER);
                continue;
            }
        }

        // basedir for quizport help files
        if ($CFG->majorrelease<=1.5) {
            $helpbasedir = $CFG->dirroot.'/lang';
        } else {
            $helpbasedir = $CFG->dataroot.'/lang';
        }
        if (! file_exists($helpbasedir)) {
            if (! @mkdir($helpbasedir)) {
                debugging('QuizPort module could not create lang dir: '.$helpbasedir, DEBUG_DEVELOPER);
                continue;
            }
        }

        // backslashes and single quotes will need to be escaped
        $replace_pairs = array("\\"=>"\\\\", "'"=>"\\'");

        foreach ($languages as $language=>$modules) {

            if ($CFG->majorrelease<=1.5) {
                $languagedir = $basedir.'/'.str_replace('_utf8', '', $language);
                $helplanguagedir = $helpbasedir.'/'.str_replace('_utf8', '', $language);
            } else {
                $languagedir = $basedir.'/'.$language.'_local';
                $helplanguagedir = $helpbasedir.'/'.$language.'_local';
            }

            if (! file_exists($languagedir)) {
                if (! @mkdir($languagedir)) {
                    debugging('QuizPort module could not create local lang dir: '.$languagedir, DEBUG_DEVELOPER);
                    continue;
                }
            }
            if (! file_exists($helplanguagedir)) {
                if (! @mkdir($helplanguagedir)) {
                    debugging('QuizPort module could not create local lang help dir: '.$helplanguagedir, DEBUG_DEVELOPER);
                    continue;
                }
            }

            foreach ($modules as $module=>$strings) {

                if ($module=='quizport' && $strings=='all') {
                    // add dummy lang file to dirroot
                    if ($languagedir==$CFG->dirroot.'/lang/en') {
                        // do nothing because we will copy the entire quizport.php next
                    } else {
                        $filepath = $CFG->dirroot.'/lang/en/quizport.php';
                        if ($fh = fopen($filepath, 'w')) {
                            fwrite($fh, '<'.'?'.'php'."\n");
                            fwrite($fh, '$'."string['modulename'] = 'QuizPort';\n");
                            fwrite($fh, '$'."string['modulenameplural'] = 'QuizPorts';\n");
                            fwrite($fh, '?'.'>'."\n");
                            fclose($fh);
                        } else {
                            debugging('QuizPort module could not create its message file', DEBUG_DEVELOPER);
                        }
                    }

                    // copy main message file and help files to dataroot
                    $fromdir = $CFG->dirroot.'/mod/quizport/lang/'.$language;
                    if (! quizport_copy_files($fromdir.'/quizport.php', $languagedir.'/quizport.php')) {
                        debugging('QuizPort module could not copy quizport messages', DEBUG_DEVELOPER);
                    }
                    if (! quizport_copy_files($fromdir.'/help/quizport', $helplanguagedir.'/help/quizport')) {
                        debugging('QuizPort module could not copy quizport help files', DEBUG_DEVELOPER);
                    }
                    continue;
                }

                $string = array();
                $filepath = $languagedir.'/'.$module.'.php';
                if (file_exists($filepath)) {
                    include ($filepath);
                    $string = array_diff_key($string, $strings);
                }
                if (! $fh = fopen($filepath, 'w')) {
                    debugging('QuizPort module could not create/access lang file: '.$filepath, DEBUG_DEVELOPER);
                    continue;
                }
                if (count($string) && count($strings)) {
                    $separator = "\n";
                } else {
                    $separator = '';
                }
                fwrite($fh, '<'.'?'.'php'."\n");
                foreach (array('string', 'strings') as $array) {
                    if ($array=='strings') {
                        fwrite($fh, $separator.'// strings required by the QuizPort module'." ($moodleversion)\n");
                    }
                    foreach ($$array as $name=>$value) {
                        fwrite($fh, '$'."string['$name'] = '".strtr($value, $replace_pairs)."';\n");
                    }
                }
                fwrite($fh, '?'.'>'."\n");
                fclose($fh);
            } // end foreach $modules
        } // end foreach $languages
    } // end foreach $missingstrings
}

function quizport_copy_files($from, $to) {
    if (! file_exists($from)) {
        return false; // not a file or directory !
    }

    // file
    if (is_file($from)) {
        $todir = dirname($to);
        if (file_exists($to)) {
            if (! @unlink($to)) {
                debugging('QuizPort could not remove file: '.$to, DEBUG_DEVELOPER);
                return false;
            }
        } else if (! file_exists($todir)) {
            // make sure $todir exists
            $dirs = explode('/', str_replace('\\', '/', $todir));
            $path = '';
            $i_max = count($dirs);
            for ($i=0; $i<$i_max; $i++) {
                $path .= (empty($path) ? '' : '/').$dirs[$i];
                if (! file_exists($path)) {
                    if (! @mkdir($path)) {
                        debugging('QuizPort could not create new directory: '.$todir, DEBUG_DEVELOPER);
                        return false;
                    }
                }
            }
        }
        if (! @copy($from, $to)) {
            debugging('QuizPort could not copy file: '.$from.' -> '.$to, DEBUG_DEVELOPER);
            return false;
        }
        return true;
    }

    // directory
    if (is_dir($from)) {
        if (! $dh = opendir($from)) {
            return false; // not readable !!
        }
        while (($item = readdir($dh)) !== false) {
            if ($item=='.' || $item=='..') {
                continue; // special hidden files
            }
            quizport_copy_files("$from/$item", "$to/$item");
        }
        closedir($dh);
        return true;
    }

    // not a file or directory (a link perhaps?)
    return false;
}
?>