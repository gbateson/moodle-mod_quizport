<?php
// get the standard XML parser supplied with Moodle
require_once($CFG->dirroot.'/lib/xmlize.php');

class quizport_file {
    var $courseid = 0;  // the course id associated with this object
    var $location = 0;  // the file's location (0 : site files, 1 : course files)
    var $coursefolder = 0;  // the course folder within the Moodle data folder (either $courseid or SITEID)

    var $basepath = ''; // the full path to the course folder (i.e. $CFG->dataroot.'/'.$coursefolder)
    var $filepath = ''; // the file's path (relative to $basepath)
    var $fullpath = ''; // the full path to this file (i.e. $basepath.'/'.$filepath)

    var $dirname  = ''; // the full path to the folder containing this file
    var $filename = ''; // the file name

    var $url = '';      // the URL of this file
    var $baseurl = '';  // the base url for this file

    var $filecontents;  // the contents of the source file

    // properties for efficiently fetching remotely hosted files using Conditional GET
    var $lastmodified = ''; // remote server's representation of time file was last modified
    var $etag = ''; // (md5?) key indentifying remote file
    var $date = ''; // remote server's representation of current time

    // properties for a unit source file (e.g. a Hot Potatoes Masher file)
    var $unitname;   // the unit name extracted from the source file
    var $unitentrytext;  // the unit entry text, extracted from the source file
    var $unitexittext;   // the unit exit text, extracted from the source file
    var $quizfiles;  // array of quizport_file objects for quizzes in this unit

    // properties of the icon for this source file type
    var $icon = 'mod/quizport/icon.gif';
    var $iconwidth = '16';
    var $iconheight = '16';
    var $iconclass = 'icon';

    // output formats which can use this source file type
    var $outputformats;
    var $best_outputformat;

    // properties of the quiz file - each one has a correspinding get_xxx() function
    var $name; // the name of the quiz that is displayed on the list of quizzes in this unit
    var $title; // the title the is displayed when this quiz is viewed in a browser
    var $entrytext; // the text, if any, that could be used on the unit's entry page
    var $exittext; // the text, if any, that could be used on the unit's entry page
    var $nextquiz; // the next quiz, if any, in this chain

    // constructor function
    function quizport_file($file, $location, $getquizfiles=false, $getquizchain=false) {
        global $CFG, $course;

        if (empty($file)) {
            return false;
        }

        if (preg_match('|^https?://|', $file)) {
            $this->url = $file;
            $this->location = QUIZPORT_LOCATION_WWW;

            if ($parse_url = parse_url($file)) {
                $this->dirname  = dirname($parse_url['path']);
                $this->filename = basename($parse_url['path']);
            }

        } else {
            $this->filepath = rtrim($file, '/\\');
            $this->location = $location;
            if (is_object($course)) {
                $this->courseid = $course->id;
            } else {
                $this->courseid = $course;
            }
            switch ($location) {
                case QUIZPORT_LOCATION_SITEFILES:
                    $this->coursefolder = SITEID;
                    break;
                case QUIZPORT_LOCATION_COURSEFILES:
                    $this->coursefolder = $this->courseid;
                    break;
            }

            $this->basepath = $CFG->dataroot.'/'.$this->coursefolder;
            $this->fullpath = $this->basepath.'/'.$this->filepath;

            $this->dirname  = dirname($this->fullpath);
            $this->filename = basename($this->fullpath);

            if ($CFG->slasharguments) {
                $this->baseurl = $CFG->wwwroot.'/file.php/'.$this->coursefolder;
            } else {
                $this->baseurl = $CFG->wwwroot.'/file.php?file=/'.$this->coursefolder;
            }
        }

        if ($getquizfiles) {
            if (! $this->quizfiles = $this->get_quizfiles_in_unitfolder()) {
                if (! $this->quizfiles = $this->get_quizfiles_in_unitfile()) {
                    if (! $this->quizfiles = $this->get_quizfiles_in_quizchain($getquizchain)) {
                        // no recognized unit or quiz files found
                        $this->quizfiles = array();
                    }
                }
            }
        }
    } // end contructor function

/*
* This function will return either
* a list of QuizPort quiz files within $this->fullpath,
* or false if there are no such files
*
* @return mixed
*         array $quizfiles ($path => $type) QuizPort quiz files in this folder
*         boolean : false : no quiz files found
*/
    function get_quizfiles_in_unitfolder() {
        $quizfiles = array();

        if (! $this->fullpath) {
            // not a local path
            return false;
        }

        if (! is_dir($this->fullpath)) {
            // not a folder
            return false;
        }

        if ($dh = @opendir($this->fullpath)) {
            $paths = array();
            while ($file = @readdir($dh)) {
                if (is_file("$this->fullpath/$file")) {
                    $paths[] = "$this->filepath/$file";
                }
            }
            closedir($dh);

            sort($paths);
            foreach ($paths as $path) {
                if ($quiz = $this->is('is_quizfile', $path, $this->location)) {
                    $quizfiles[] = $quiz;
                }
            }
        }

        if (count($quizfiles)) {
            return $quizfiles;
        } else {
            return false;
        }
    }

    // returns an array of quizport_file objects if $filename is a head of a quiz chain, or false otherwise
    function get_quizfiles_in_quizchain($getquizchain) {
        $quizfiles = array();

        if ($this->location==QUIZPORT_LOCATION_WWW) {
            $path = $this->url;
        } else {
            $path = $this->filepath;
        }

        while ($path && ($quiz = $this->is('is_quizfile', $path, $this->location))) {

            // add this quiz
            $quizfiles[] = $quiz;

            if ($getquizchain) {
                // get next quiz (if any)
                if ($path = $quiz->get_nextquiz()) {
                    // to prevent infinite loops on chains, we check that
                    // the next quiz is not one of the earlier quizzes
                    foreach ($quizfiles as $quizfile) {
                        if ($quizfile->filepath==$path) {
                            $path = false;
                        }
                    }
                }
            } else {
                // force end of loop
                $path = false;
            }
        }

        if (count($quizfiles)) {
            return $quizfiles;
        } else {
            return false;
        }
    }

    // return array of
    function get_quizfiles_in_unitfile() {
        $quizfiles = array();

        if ($this->location==QUIZPORT_LOCATION_WWW) {
            $path = $this->url;
        } else {
            $path = $this->filepath;
        }

        if (! $paths = $this->is('is_unitfile', $path, $this->location)) {
            return false;
        }

        foreach ($paths as $path) {
            if ($quiz = $this->is('is_quizfile', $path, $this->location)) {
                $quizfiles[] = $quiz;
            }
        }

        if (count($quizfiles)) {
            return $quizfiles;
        } else {
            return false;
        }
    }

/*
* Given a class method name, a full path to a file and relative path to plugins directory,
* this function will get quiz type classes from the plugins directory (and subdirectories),
* and search the classes for a method which returns a non-empty result
*
* @param string $methodname : name of a method to be used in the classes in the plugins directory
* @param string $fullpath :  to a file, which may be a QuizPort quiz or unit file
* @return mixed : whatever the result that is return from the $methodname called on the classes
*/
    function is($methodname, $path, $location) {
        $result = false;

        $types = quizport_get_classes('file');
        foreach ($types as $type) {

            //if ($result = $type::$methodname($fullpath)) {
            $object = new $type($path, $location);
            if (method_exists($object, $methodname)) {

                // give the quiz object access to this object
                if ($result = $object->$methodname()) {
                    // if this is the first unit/quiz file to be recognized, then store the name
                    // because if $form->namesource==QUIZPORT_NAMESOURCE_QUIZ,
                    // $this->unitname may be used later as the name of the QuizPort activity
                    if (empty($this->unitname)) {
                        $this->unitname = $object->get_name();
                    }
                    if (empty($this->unitentrytext)) {
                        $this->unitentrytext = $object->get_entrytext();
                    }
                    if (empty($this->unitexittext)) {
                        $this->unitexittext = $object->get_exittext();
                    }
                    break;
                }
            }
        }
        return $result;
    }

    // returns quizport_file object if $filename is a quiz file, or false otherwise
    function is_quizfile() {
        return false;
    }

    // return array of filepaths if $filename is a unit file, or false otherwise
    function is_unitfile() {
        return false;
    }

    // returns name of quiz that is displayed in the list of quizzes
    function get_name() {
        return '';
    }

    // returns title of quiz when it is viewed in a browser
    function get_title() {
        return '';
    }

    // returns the entry text for a quiz
    function get_entrytext() {
        return '';
    }

    // returns the exit text for a quiz
    function get_exittext() {
        return '';
    }

    // returns $filepath of next quiz if there is one, or false otherwise
    function get_nextquiz() {
        return false;
    }

    // returns an <img> tag for the icon for this source file type
    function get_icon() {
        global $CFG;
        if (preg_match('/^(?:https?:)?\/+/', $this->icon)) {
            $icon = $this->icon;
        } else {
            $icon = $CFG->wwwroot.'/'.$this->icon;
        }
        return '<img src="'.$icon.'" width="'.$this->iconwidth.'" height="'.$this->iconheight.'" class="'.$this->iconclass.'" />';
    }

    // property access functions

    // returns file (=either url or filepath)
    function get_file() {
        if ($this->location==QUIZPORT_LOCATION_WWW) {
            return $this->url;
        }
        if ($this->filepath) {
            return $this->filepath;
        }
        return false;
    }

    // returns location (0 : coursefiles; 1 : site files; false : undefined) of quiz source file
    function get_location($courseid) {
        if ($this->coursefolder) {
            if ($this->coursefolder==$courseid) {
                return QUIZPORT_LOCATION_COURSEFILES;
            }
            if ($this->coursefolder==SITEID) {
                return QUIZPORT_LOCATION_SITEFILES;
            }
        }
        if ($this->url) {
            return QUIZPORT_LOCATION_WWW;
        }
        return false;
    }

    function filemtime($lastmodified, $etag) {
        if ($this->fullpath) {
            if (file_exists($this->fullpath)) {
                return filemtime($this->fullpath);
            } else {
                debugging('file not found: '.$this->fullpath, DEBUG_DEVELOPER);
                return 0;
            }
        }
        if ($this->url) {
            $headers = array(
                'If-Modified-Since'=>$lastmodified, 'If-None-Match'=>$etag
                // 'If-Modified-Since'=>'Wed, 23 Apr 2008 17:53:50 GMT',
                // 'If-None-Match'=>'"52237ffc6aa5c81:16d9"'
            );
            if ($this->get_filecontents_url($headers)) {
                if ($this->lastmodified) {
                    $filemtime = strtotime($this->lastmodified);
                } else {
                    $filemtime = strtotime($lastmodified);
                }
                if ($this->date) {
                    $filemtime += (time() - strtotime($this->date));
                }
                return $filemtime;
            } else {
                debugging('remote file not accesisble: '.$this->url, DEBUG_DEVELOPER);
                return 0;
            }
        }
        // not a local file or a remote file ?!
        return 0;
    }

    function get_filecontents() {
        if (isset($this->filecontents)) {
            return $this->filecontents ? true : false;
        }

        // initialize $this->filecontent
        $this->filecontents = false;

        if ($this->location==QUIZPORT_LOCATION_WWW) {
            if (! $this->url) {
                // no url given - shouldn't happen
                return false;
            }
            if (! $this->get_filecontents_url()) {
                // url is (no longer) accessible
                return false;
            }
        } else {
            if (! $this->fullpath) {
                // no filepath given - shouldn't happen
                return false;
            }

            if (! is_readable($this->fullpath)) {
                // file does not exist or is not readable
                return false;
            }

            // get the file contents
            if (! $this->filecontents = file_get_contents($this->fullpath)) {
                // nothing in the file (or some problem with "file_set_bodycontents")
                return false;
            }
        }

        // file contents were successfully read

        // remove BOMs - http://en.wikipedia.org/wiki/Byte_order_mark
        switch (true) {
            case substr($this->filecontents, 0, 4)=="\xFF\xFE\x00\x00":
                $start = 4;
                $encoding = 'UTF-32LE';
                break;
            case substr($this->filecontents, 0, 4)=="\x00\x00\xFE\xFF":
                $start = 4;
                $encoding = 'UTF-32BE';
                break;
            case substr($this->filecontents, 0, 2)=="\xFF\xFE":
                $start = 2;
                $encoding = 'UTF-16LE';
                break;
            case substr($this->filecontents, 0, 2)=="\xFE\xFF":
                $start = 2;
                $encoding = 'UTF-16BE';
                break;
            case substr($this->filecontents, 0, 3)=="\xEF\xBB\xBF":
                $start = 3;
                $encoding = 'UTF-8';
                break;
            default:
                $start = 0;
                $encoding = '';
        }

        // remove BOM, if necessary
        if ($start) {
            $this->filecontents = substr($this->filecontents, $start);
        }

        // convert to UTF-8, if necessary
        if ($encoding=='' || $encoding=='UTF-8') {
            // do nothing
        } else if (function_exists('iconv')) {
            $this->filecontents = iconv($encoding, 'UTF-8', $this->filecontents);
        } else if (function_exists('mb_convert_encoding')) {
            $this->filecontents = mb_convert_encoding($this->filecontents, 'UTF-8', $encoding);
        }

        return true;
    }

    function get_filecontents_url($headers=null) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/filelib.php');

        $fullresponse = download_file_content($this->url, $headers, null, true);
        foreach ($fullresponse->headers as $header) {
            if ($pos = strpos($header, ':')) {
                $name = trim(substr($header, 0, $pos));
                $value = trim(substr($header, $pos+1));
                switch ($name) {
                    case 'Last-Modified': $this->lastmodified = trim($value); break;
                    case 'ETag': $this->etag = trim($value); break;
                    case 'Date': $this->date = trim($value); break;
                }
            }
        }
        if ($fullresponse->status==200) {
            $this->filecontents = $fullresponse->results;
            return true;
        }
        if ($fullresponse->status==304) {
            return true;
        }
        return false;
    }

    function compact_filecontents() {
        if (isset($this->filecontents)) {
            $this->filecontents = preg_replace('/(?<=>)'.'\s+'.'(?=<)/s', '', $this->filecontents);
        }
    }

    // return the file type i.e. class name without the leading "quizport_file_"
    function get_type($class='') {
        if ($class=='') {
            $class = get_class($this);
        }
        // class is either "quizport_file_" or "quizport_output_"
        return preg_replace('/^quizport_[a-z]+_/', '', $class);
    }

    // return output formats for this file type
    function get_outputformats() {
        if (! isset($this->outputformats)) {
            $this->outputformats = array();

            $thistype = $this->get_type();
            $classes = quizport_get_classes('output');
            foreach ($classes as $class) {
                if ($filetypes = quizport_get_class_constant($class, 'filetypes')) {
                    if (in_array($thistype, $filetypes)) {
                        $this->outputformats[] = $this->get_type($class);
                    }
                }
            }
        }
        return $this->outputformats;
    }

    // return best output format for this file type
    // (eventually this should take account of current device and browser)
    function get_best_outputformat() {
        if (! isset($this->best_outputformat)) {
            // the default outputformat is the same as the sourcefile format
            // assuming class name starts with "quizport_file_"
            $this->best_outputformat = substr(get_class($this), 14);
        }
        return $this->best_outputformat;
    }


    // synchonize file and Moodle settings
    function synchronize_moodle_settings(&$quiz) {
        return false;
    }
} // end class
?>