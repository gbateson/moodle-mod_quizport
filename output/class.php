<?php

class quizport_output {
    // source file types with which this output format can be used
    var $filetypes = array();

    var $xmldeclaration; // XML declaration
    var $doctype;        // DOCTYPE tag
    var $htmltag;        // HTML tag
    var $headattributes; // HEAD tag attributes
    var $headcontent;    // HEAD content
    var $bodyattributes; // BODY tag attributes
    var $bodycontent;    // BODY content

    var $cache;          // object to store the quizport_cache record for the quiz
    var $cache_uptodate; // set to true or false depending on whether the cache content is uptodate or not

    var $cache_CFG_fields = array(
        // these $CFG fields must match those in the "quizport_cache" table
        // "wwwroot" is not stored explicitly because it is included in the md5key
        'slasharguments','quizport_enableobfuscate','quizport_enableswf'
    );
    var $cache_quiz_fields = array(
        // these fields in the quiz record must match those in the "quizport_cache" table
        // "outputformat" is not stored explicitly because it is included in the md5key
        'name','sourcefile','sourcetype','sourcelocation','configfile','configlocation',
        'navigation','stopbutton','stoptext','title','usefilters','useglossary','usemediafilter',
        'studentfeedback','studentfeedbackurl','timelimit','delay3','clickreporting'
    );
    var $cache_text_fields = array(
        // these fields need to be escaped (on Moodle <= 1.9)
        'slasharguments','quizport_enableobfuscate','quizport_enableswf','name',
        'sourcefile','sourcetype','sourcelastmodified','sourceetag',
        'configfile','configlastmodified','configetag',
        'usemediafilter','studentfeedbackurl','stoptext'
    );
    var $cache_remote_fields = array(
        // these fields from the source and config file objects are stored in the cache
        'lastmodified','etag'
    );
    var $cache_content_fields = array(
        // these fields will be serialized and then stored in the "content" field of the "quizport_cache" table
        'xmldeclaration','doctype','htmltag','headattributes','headcontent','bodyattributes','bodycontent'
    );

    var $response_text_fields = array(
        // these fields will be displayed one per row for each response on the quiz attempt report
        'correct', 'ignored', 'wrong'
    );

    var $response_num_fields = array(
        // these fields will be displayed on a single row for each response on the quiz attempt report
        'score', 'weighting', 'hints', 'clues', 'checks'
    );

    // these fields will appear on the detailed statistics report
    var $detailed_string_fields = array('correct', 'wrong', 'ignored');
    var $detailed_number_fields = array('hints', 'clues', 'checks');
    var $detailed_score_field = 'score';

    // the name of the $_POST fields holding the score and details
    // and the xml tag within the details that holds the results
    var $scorefield = 'score';
    var $detailsfield = 'detail';
    var $xmlresultstag = 'hpjsresult';

    // the two fields that will be used to determine the duration of a quiz attempt
    //     starttime/endtime are recorded by the client (and may not be trustworthy)
    //     resumestart/resumefinish are recorded by the server (but include transfer time to and from client)
    var $durationstartfield = 'resumestart';
    var $durationfinishfield = 'resumefinish';

    // statistical reports available for this file type
    var $reports = array();

    // most outputformats use the quizport cache
    // but those that don't can switch this flag off
    var $use_quizport_cache = true;

    // the id and onload function for the embedded object used for QUIZPORT_NAVIGATION_EMBED
    var $embed_object_id  = 'quizport_embed_object';
    var $embed_object_onload  = 'set_embed_object_height';

    // constructor function
    function quizport_output(&$quiz) {
        $fields = get_object_vars($quiz);
        foreach ($fields as $field => $value) {
            $this->$field = $quiz->$field;
        }

        $this->framename = optional_param('framename', '', PARAM_ALPHA);

        switch ($this->navigation) {
            case QUIZPORT_NAVIGATION_BAR:
                $this->usemoodletheme = true;
                break;
            case QUIZPORT_NAVIGATION_FRAME:
                // use the moodle theme only on the "top" frame
                // i.e. not on the window or the "main" frame
                $this->usemoodletheme = ($this->framename=='top');
                break;
            case QUIZPORT_NAVIGATION_EMBED:
                // use the moodle theme on the window but not on the embedded document
                $this->usemoodletheme = ($this->framename=='');
                break;
            case QUIZPORT_NAVIGATION_ORIGINAL:
            case QUIZPORT_NAVIGATION_NONE:
            default:
                $this->usemoodletheme = false;
                break;
        } // end switch

        $this->set_reports();
    }

    // does this output format allow quiz attempts to be reviewed?
    function provide_review() {
        return false;
    }

    // does this output format allow quiz attempts to be resumed?
    function provide_resume() {
        return false;
    }

    // does this output format allow a clickreport
    // show a click trail of what students clicked
    function provide_clickreport() {
        return false;
    }

    // can the current quiz attempt be reviewed now?
    function can_review() {
        global $QUIZPORT;
        if ($this->provide_review() && $QUIZPORT->quiz->reviewoptions) {
            if ($attempt = $QUIZPORT->get_quizattempt()) {
                if ($QUIZPORT->quiz->reviewoptions & QUIZPORT_REVIEW_DURINGATTEMPT) {
                    // during attempt
                    if ($attempt->status==QUIZPORT_STATUS_INPROGRESS) {
                        return true;
                    }
                }
                if ($QUIZPORT->quiz->reviewoptions & QUIZPORT_REVIEW_AFTERATTEMPT) {
                    // after attempt (but before quiz closes)
                    if ($attempt->status==QUIZPORT_STATUS_COMPLETED) {
                        return true;
                    }
                    if ($attempt->status==QUIZPORT_STATUS_ABANDONED) {
                        return true;
                    }
                    if ($attempt->status==QUIZPORT_STATUS_TIMEDOUT) {
                        return true;
                    }
                }
                if ($QUIZPORT->quiz->reviewoptions & QUIZPORT_REVIEW_AFTERCLOSE) {
                    // after the quiz closes
                    if ($QUIZPORT->quiz->timeclose < $QUIZPORT->time) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // can the current unit/quiz attempt be paused and resumed later?
    function can_resume($type) {
        global $QUIZPORT;
        if ($type=='unit' || ($type=='quiz' && $this->provide_resume())) {
            if (isset($QUIZPORT->$type) && $QUIZPORT->$type->allowresume) {
                return true;
            }
        }
        return false;
    }

    // can the current unit/quiz be restarted after the current attempt finishes?
    function can_restart($type) {
        global $QUIZPORT;
        if (isset($QUIZPORT->$type) && $QUIZPORT->$type->attemptlimit) {
            if ($attempts = $QUIZPORT->get_attempts($type)) {
                if (count($attempts) >= $QUIZPORT->$type->attemptlimit) {
                    return false;
                }
            }
        }
        return true;
    }

    function can_continue() {
        if ($this->can_resume('unit')) {
            if ($this->can_resume('quiz')) {
                return QUIZPORT_CONTINUE_RESUMEQUIZ;
            } else if ($this->can_restart('quiz')) {
                return QUIZPORT_CONTINUE_RESTARTQUIZ;
            }
        }
        if ($this->can_restart('unit')) {
            return QUIZPORT_CONTINUE_RESTARTUNIT;
        } else {
            return QUIZPORT_CONTINUE_ABANDONUNIT;
        }
    }

    function can_clickreport() {
        global $QUIZPORT;
        if ($this->provide_clickreport() && isset($QUIZPORT->quiz) && $QUIZPORT->quiz->clickreporting) {
            return true;
        } else {
            return false;
        }
    }

    function generate($cacheonly=false) {
        global $CFG, $QUIZPORT;

        // if necessary, print container page and associated frames
        $basetag = '';
        if ($this->navigation==QUIZPORT_NAVIGATION_FRAME || $this->navigation==QUIZPORT_NAVIGATION_EMBED) {
            if ($cacheonly) {
                $this->framename = 'main';
                $this->usemoodletheme = false;
            } else if ($this->navigation==QUIZPORT_NAVIGATION_FRAME) {
                if ($this->framename=='') {
                    $this->print_frameset();
                    die;
                }
                if ($this->framename=='top') {
                    $this->print_topframe();
                    die;
                }
            } else if ($this->navigation==QUIZPORT_NAVIGATION_EMBED) {
                if ($this->framename=='') {
                    $this->print_embed_object_page();
                    die;
                }
            }
            // otherwise we print the "main" frame below

            // set basetag to ensure links and forms can escape from frame
            $basetag = $this->basetag();
        }

        // try to get content from cache
        $this->get_fields_from_cache();

        if ($cacheonly && $this->cache_uptodate) {
            return true;
        }

        // do pre-processing, if required
        $this->preprocessing();

        // generate the main parts of the page
        $this->set_xmldeclaration();
        $this->set_doctype();
        $this->set_htmltag();
        $this->set_headattributes();
        $this->set_headcontent();
        $this->set_bodyattributes();
        $this->set_bodycontent();

        // save content to cache (if necessary)
        $this->set_fields_in_cache();

        if ($cacheonly) {
            return true;
        }

        // do post-processing, if required
        $this->postprocessing();

        if (! $this->bodycontent) {
            if (file_exists($this->source->fullpath) && is_readable($this->source->fullpath)) {
                $QUIZPORT->print_error(get_string('error_emptyfile', 'quizport', $this->source->fullpath));
            } else {
                $QUIZPORT->print_error(get_string('error_nofile', 'quizport', $this->source->fullpath));
            }
        }

        if ($this->usemoodletheme) {

            $QUIZPORT->print_header(
                $this->get_name(),     // $title
                $this->get_navlinks(), // $morenavlinks
                $this->bodyattributes, // $bodytags
                $this->headcontent,    // $meta
                $this->xmldeclaration  // < ?xml version="1.0" ? >
            );

            $QUIZPORT->print_tabs();

            if ($CFG->majorrelease<=1.9) {
                $QUIZPORT->print_main_table_start();
                $QUIZPORT->print_left_column();
                $QUIZPORT->print_middle_column_start();
            }

            print $this->bodycontent;

            if ($CFG->majorrelease<=1.9) {
                $QUIZPORT->print_middle_column_finish();
                $QUIZPORT->print_right_column();
                $QUIZPORT->print_main_table_finish();
            }

            $QUIZPORT->print_footer();

        } else {

            // show  the quiz without Moodle header's and themes
            print $this->xmldeclaration;
            print $this->doctype;
            print $this->htmltag;
            print '<head'.$this->headattributes.">\n";
            print $this->headcontent.$basetag."\n";
            print "</head>\n";
            print '<body '.$this->bodyattributes.">\n";
            print $this->bodycontent;
            print "</body>\n";
            print "</html>\n";
        }
    }

    function basetag() {
        global $CFG;
        if (empty($CFG->framename)) {
            $framename = '_top';
        } else {
            $framename = $CFG->framename;
        }
        return '<base target="'.$framename.'" href="" />';
        // Note: href is required for strict xhtml
    }

    function fix_targets() {
        global $CFG;
        if (empty($CFG->framename)) {
            $framename = '_top';
        } else {
            $framename = $CFG->framename;
        }
        $this->bodycontent .= ''
            .'<script type="text/javascript">'."\n"
            .'//<![CDATA['."\n"
            ."	var obj = document.getElementsByTagName('a');\n"
            ."	if (obj) {\n"
            ."		var i_max = obj.length;\n"
            ."		for (var i=0; i<i_max; i++) {\n"
            ."			if (obj[i].href && ! obj[i].target) {\n"
            ."				obj[i].target = '$framename';\n"
            ."			}\n"
            ."		}\n"
            ."		var obj = null;\n"
            ."	}\n"
            ."	var obj = document.getElementsByTagName('form');\n"
            ."	if (obj) {\n"
            ."		var i_max = obj.length;\n"
            ."		for (var i=0; i<i_max; i++) {\n"
            ."			if (obj[i].action && ! obj[i].target) {\n"
            ."				obj[i].target = '$framename';\n"
            ."			}\n"
            ."		}\n"
            ."		var obj = null;\n"
            ."	}\n"
            .'//]]>'."\n"
            .'</script>'."\n"
        ;
    }

    function print_frameset() {
        // print frameset containing "top" and "main" frames
        global $CFG, $QUIZPORT;

        $title_top = get_string('navigation', 'quizport');
        $title_main = get_string('modulename', 'quizport');

        $src_top = $QUIZPORT->format_url('view.php', '', array('framename'=>'top'));
        $src_main = $QUIZPORT->format_url('view.php', '', array('framename'=>'main'));

        if (empty($CFG->quizport_lockframe)) {
            $lock_frameset = '';
            $lock_top = '';
            $lock_main = '';
        } else {
            $lock_frameset = ' border="0" frameborder="0" framespacing="0"';
            $lock_top = ' noresize="noresize" scrolling="no"';
            $lock_main = ' noresize="noresize"';
        }

        if (empty($CFG->quizport_frameheight)) {
            $rows =  85; // default
        } else {
            $rows = $CFG->quizport_frameheight;
        }

        print '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">'."\n";
        print '<html>'."\n";
        print '<head>'."\n";
        print '<meta http-equiv="content-type" content="text/html; charset='.get_string('thischarset').'" />'."\n";
        print $this->basetag()."\n";
        print '<title>'.$this->get_name().'</title>'."\n";
        print '</head>'."\n";
        print '<frameset rows="'.$rows.',*".'.$lock_frameset.'>'."\n";
        print '<frame title="'.$title_top.'" src="'.$src_top.'"'.$lock_top.' />'."\n";
        print '<frame title="'.$title_main.'" src="'.$src_main.'"'.$lock_main.' />'."\n";
        print '<noframes>'."\n";
        print '<p>'.get_string('framesetinfo').'</p>'."\n";
        print '<ul>'."\n";
        print '<li><a href="'.$src_top.'">'.$title_top.'</a></li>'."\n";
        print '<li><a href="'.$src_main.'">'.$title_main.'</a></li>'."\n";
        print '</ul>'."\n";
        print '</noframes>'."\n";
        print '</frameset>'."\n";
        print '</html>'."\n";
    }

    function print_topframe() {
        // print "top" frame, containing Moodle navigation bar
        global $QUIZPORT;
        $QUIZPORT->print_header(
            $this->get_name(),     // $title
            $this->get_navlinks(), // $morenavlinks
            $this->bodyattributes, // $bodytags
            $this->headcontent, // $meta
            $this->xmldeclaration  // < ?xml version="1.0" ? >
        );
        $QUIZPORT->print_footer('empty');
    }

    function print_embed_object_page() {
        // print a page embedded in an object in a standard Moodle page

        // for XHTML 1.0 Strict compatability, the embedded page should be implemented
        // using an <object> not an <iframe>. However, IE <object>'s are problematic
        // (links and forms cannot escape), so we use conditional comments to display
        // an <iframe> in IE and an <object> in other browsers

        global $CFG, $QUIZPORT;

        $QUIZPORT->print_header(
            $this->get_name(),     // $title
            $this->get_navlinks(), // $morenavlinks
            $this->bodyattributes, // $bodytags
            $this->headcontent, // $meta
            $this->xmldeclaration  // < ?xml version="1.0" ? >
        );

        // set object attributes
        $id = $this->embed_object_id;
        $width = '100%';
        $height = '100%';
        $onload_function = $this->embed_object_onload;
        $src = $QUIZPORT->format_url('view.php', '', array('framename'=>'main'));

        // external javascript to adjust height of iframe
        print '<script type="text/javascript" src="'.$CFG->wwwroot.'/mod/quizport/iframe.js"></script>'."\n";

        // print the html element to hold the embedded html page
        // Note: the iframe in IE needs a "name" attribute for the resizing to work
        print '<!--[if IE]>'."\n";
        print '<iframe name="'.$id.'" id="'.$id.'" src="'.$src.'" width="'.$width.'" height="'.$height.'"></iframe>'."\n";
        print '<![endif]-->'."\n";
        print '<!--[if !IE]> <-->'."\n";
        print '<object id="'.$id.'" type="text/html" data="'.$src.'" width="'.$width.'" height="'.$height.'"></object>'."\n";
        print '<!--> <![endif]-->'."\n";

        // javascript to add onload event handler - we do this here because
        // an object tag should have no onload attribute in XHTML 1.0 Strict
        print '<script type="text/javascript">'."\n";
        print '//<![CDATA['."\n";
        print "var obj = document.getElementById('$id');\n";
        print "if (obj) {\n";
        print "	if (obj.addEventListener) {\n";
        print "		obj.addEventListener('load', $onload_function, false);\n";
        print "	} else if (obj.attachEvent) {\n";
        print "		obj.attachEvent('onload', $onload_function);\n";
        print "	} else {\n";
        print "		obj['onload'] = $onload_function;\n";
        print "	}\n";
        print "}\n";
        print "obj = null;\n";
        print '//]]>'."\n";
        print '</script>'."\n";

        $QUIZPORT->print_footer();
    }

    // functions to set and get cached content

    function get_cache_md5key() {
        global $CFG;
        return md5($this->outputformat.current_theme().$CFG->wwwroot);
    }

    function get_fields_from_cache() {
        global $CFG, $DB;

        if (isset($this->cache_uptodate)) {
            return $this->cache_uptodate;
        }

        // assume cache is not up-to-date
        $this->cache_uptodate = false;

        if (empty($CFG->quizport_enablecache)) {
            return false; // cache not enabled
        }

        $select = "quizid=$this->id AND md5key='".$this->get_cache_md5key()."'";
        if (! $this->cache = $DB->get_records_select('quizport_cache', $select)) {
            return false; // no cached content for this quiz (+ outputformat + currenttheme + wwwroot)
        }

        // there should only be one record returned from the cache
        // but there have been reports of more than one being found,
        // so the we get the first record and delete any others
        $ids = array_keys($this->cache);
        $id = array_shift($ids);
        $this->cache = $this->cache[$id];
        if ($ids = implode(',', $ids)) {
            $DB->delete_records_select('quizport_cache', "id IN ($ids)");
        }

        foreach ($this->cache_CFG_fields as $field) {
            if ($this->cache->$field != $CFG->$field) {
                return false; // $CFG settings have changed
            }
        }
        foreach ($this->cache_quiz_fields as $field) {
            if ($this->cache->$field != $this->$field) {
                return false; // quiz settings have changed
            }
        }

        // custom fields
        if (isset($this->source) && $this->cache->timemodified < $this->source->filemtime($this->cache->sourcelastmodified, $this->cache->sourceetag)) {
            return false; // sourcefile file has been modified
        }
        if (isset($this->source->config) && $this->cache->timemodified < $this->source->config->filemtime($this->cache->configlastmodified, $this->cache->configetag)) {
            return false; // config file has been modified
        }
        if ($this->useglossary) {
            $select = "
               course = {$this->source->courseid}
               AND module = 'glossary'
               AND action IN ('add entry','approve entry','update entry','delete entry')
               AND time > {$this->cache->timemodified}
            ";
            if ($DB->record_exists_select('log', $select)) {
                return false; // glossary entries (for this course) have been modified
            }
        }

        // if we get this far then the cache content is uptodate

        // transfer cache content to this quiz object
        $content = unserialize(base64_decode($this->cache->content));
        foreach ($this->cache_content_fields as $field) {
            $this->$field = $content->$field;
        }
        $this->cache_uptodate = true;
        return $this->cache_uptodate;
    }

    function set_fields_in_cache() {
        global $CFG, $DB;

        if (empty($CFG->quizport_enablecache)) {
            return; // cache not enabled
        }

        if ($this->cache_uptodate) {
            return; // cache is already uptodate
        }

        if (! $this->cache) {
            $this->cache = new stdClass();
        }

        // add special fields to cache record
        $this->cache->quizid = $this->id;
        $this->cache->md5key = $this->get_cache_md5key();
        $this->cache->timemodified = time();

        // transfer $CFG fields to cache record
        foreach ($this->cache_CFG_fields as $field) {
            $this->cache->$field = $CFG->$field;
        }

        // transfer quiz fields to cache record
        foreach ($this->cache_quiz_fields as $field) {
            $this->cache->$field = $this->$field;
        }

        // transfer remote access fields to cache record
        foreach ($this->cache_remote_fields as $field) {
            $sourcefield = 'source'.$field;
            $configfield = 'config'.$field;
            $this->cache->$sourcefield = $this->source->$field;
            $this->cache->$configfield = $this->source->config->$field;
        }

        // add slashes to text fields, if necessary
        if ($CFG->majorrelease<=1.9) {
            foreach ($this->cache_text_fields as $field) {
                $this->cache->$field = addslashes($this->cache->$field);
            }
        }

        // create content object
        $content = new stdClass();
        foreach ($this->cache_content_fields as $field) {
            $content->$field = $this->$field;
        }

        // serialize the $content object
        $this->cache->content = base64_encode(serialize($content));

        // add / update the cache record
        if (isset($this->cache->id)) {
            if (! $DB->update_record('quizport_cache', $this->cache)) {
                print_error('error_updaterecord', 'quizport', '', 'quizport_cache');
            }
        } else {
            if (! $this->cache->id = $DB->insert_record('quizport_cache', $this->cache)) {
                print_error('error_insertrecord', 'quizport', '', 'quizport_cache');
            }
        }

        // cache record was successfully updated/inserted
        $this->cache_uptodate = true;
        return $this->cache_uptodate;
    }

    // functions to generate browser content

    function preprocessing() {
        // pre-processing for this output format
        // e.g. convert source to ideal format for this output format
    }
    function set_xmldeclaration() {
        if (! isset($this->xmldeclaration)) {
            $this->xmldeclaration = '';
        }
    }
    function set_doctype() {
        if (! isset($this->doctype)) {
            $this->doctype = '';
        }
    }
    function set_htmltag() {
        if (! isset($this->htmltag)) {
            $this->htmltag = '<html>';
        }
    }
    function set_headattributes() {
        if (! isset($this->headattributes)) {
            $this->headattributes = '';
        }
    }
    function set_headcontent() {
        if (! isset($this->headcontent)) {
            $this->headcontent = '';
        }
    }
    function set_bodyattributes() {
        if (! isset($this->bodyattributes)) {
            $this->bodyattributes = '';
        }
    }
    function set_bodycontent() {
        if (! isset($this->bodycontent)) {
            $this->bodycontent = '';
        }
    }
    function postprocessing() {
        // procesessing for this output format after content has been retrieved from cache
        // intended for fixes to $this->headcontent and $this->bodycontent
        // that are not to be included in the cached data
        // if you want to fix $this->headcontent and $this->bodycontent
        // before caching, add your own "set_bodycontent()" method
    }
    function get_name() {
        return $this->source->get_name();
    }
    function get_title() {
        global $QUIZPORT;
        switch ($this->title & QUIZPORT_TITLE_SOURCE) {
            case QUIZPORT_TEXTSOURCE_FILE:
                $title = $this->source->get_title();
                break;
            case QUIZPORT_TEXTSOURCE_FILENAME:
                $title = basename($this->sourcefile);
                break;
            case QUIZPORT_TEXTSOURCE_FILEPATH:
                $title = str_replace(array('/', '\\'), ' ', $this->sourcefile);
                break;
            case QUIZPORT_TEXTSOURCE_SPECIFIC:
            default:
                $title = $this->name;
        }
        if ($this->title & QUIZPORT_TITLE_UNITNAME) {
            $title = $QUIZPORT->quizport->name.': '.$title;
        }
        if ($this->title & QUIZPORT_TITLE_SORTORDER) {
            $title .= ' ('.$this->sortorder.')';
        }
        if (method_exists($this->source, 'utf8_to_entities')) {
            $title = $this->source->utf8_to_entities($title);
        }
        return format_string($title);
    }
    function get_navlinks() {
        global $CFG, $QUIZPORT;
        if ($QUIZPORT->unumber && $QUIZPORT->quizid) {
            return array(
                array(
                    'name' => get_string('quizzes', 'quizport'),
                    'link' => $CFG->wwwroot."/mod/quizport/view.php?id={$QUIZPORT->modulerecord->id}&amp;unumber={$QUIZPORT->unumber}",
                    'type' => 'activityinstance'
                ),
                array(
                    'name' => format_string($this->get_name()),
                    'link' => $CFG->wwwroot."/mod/quizport/view.php?id={$QUIZPORT->modulerecord->id}&amp;unumber={$QUIZPORT->unumber}&amp;quizid={$QUIZPORT->quizid}",
                    'type' => 'activityinstance'
                )
            );
        }
        // navlinks are not required
        return '';
    }

    // utility functions for the html files

    function remove_blank_lines($str) {
        // standardize line endings and remove trailing white space and blank lines
        $str = preg_replace('/\s+[\r\n]/s', "\n", $str);
        return $str;
    }

    function single_line($str) {
        return trim(preg_replace('/\s+/s', ' ', $str));
    }

    function tagpattern($tag, $attribute='', $returncontent=true, $before='', $after='') {
        // $0 : entire match
        // if $attribute is empty
        //     $1 : all tag attrbutes
        //     $2 : content (if required)
        // if $attribute is NOT empty
        //     $1 : all tag attrbutes
        //     $2 : first quote of required attibute
        //     $3 : value of required attibute
        //     $4 : closing quote of required attibute
        //     $5 : content (if required)
        if ($attribute) {
            $attribute .= '=(["\'])(.*?)(\\2)[^>]*';
        }
        if ($returncontent) {
            $content = '(.*?)<\/'.$tag.'>';
        } else {
            $content = '';
        }
        return '/'.$before.'<'.$tag.'([^>]*'.$attribute.')>'.$content.$after.'/is';
    }

    function fix_css_definitions($container, $css_selector, $css_definition, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $css_selector = str_replace('\\'.$quote, $quote, $css_selector);
            $css_definition = str_replace('\\'.$quote, $quote, $css_definition);
        }

        $selectors = array();
        foreach (explode(',', $css_selector) as $selector) {
            if ($selector = trim($selector)) {
                switch (true) {
                    case preg_match('/^html\b/i', $selector):
                        // leave "html" as it is
                        $selectors[] = "$selector";
                        break;
                    case preg_match('/^body\b/i', $selector):
                        // replace "body" with the container element
                        $selectors[] = "$container";
                        // remove font, backgroud and color from the css definition
                        //$search = "/\b(font-family|background-color|color)\b[^;]*;/";
                        //$css_definition = preg_replace($search, '/* \\0 */', $css_definition);
                        break;
                    default:
                        // restrict other CSS selctors to affect only the content of the container element
                        $selectors[] = "$container $selector";
                }
            }
        }
        return implode(",\n", $selectors)."\n".'{'.$css_definition.'}';
    }

    function fix_onload($onload, $script_tags=false) {
        static $attacheventid = 0;

        $str = '';
        if ($script_tags) {
            $str .= "\n".'<script type="text/javascript">'."\n"."//<![CDATA[\n";
        }
        if ($attacheventid && $attacheventid==$this->id) {
            // do nothing
        } else {
            // only do this once per quiz
            $attacheventid = $this->id;
            $str .= ''
                ."/**\n"
                ." * Based on http://phrogz.net/JS/AttachEvent_js.txt - thanks!\n"
                ." * That code is copyright 2003 by Gavin Kistner, !@phrogz.net\n"
                ." * and is covered under the license viewable at http://phrogz.net/JS/_ReuseLicense.txt\n"
                ." */\n"

                ."function quizportAttachEvent(obj, evt, fnc, useCapture) {\n"
                ."	// obj : an HTML element\n"
                ."	// evt : the name of the event (without leading 'on')\n"
                ."	// fnc : the name of the event handler funtion\n"
                ."	// useCapture : boolean (default = false)\n"

                ."	if (typeof(fnc)=='string') {\n"
                ."		fnc = new Function(fnc);\n"
                ."	}\n"

                ."	// transfer object's old event handler (if any)\n"
                ."	var onevent = 'on' + evt;\n"
                ."	if (obj[onevent]) {\n"
                ."		var old_event_handler = obj[onevent];\n"
                ."		obj[onevent] = null;\n"
                ."		quizportAttachEvent(obj, evt, old_event_handler, useCapture);\n"
                ."	}\n"

                ."	// create key for this event handler\n"
                ."	var s = fnc.toString();\n"
                .'	s = s.replace(new RegExp("[; \\\\t\\\\n\\\\r]+", "g"), "");'."\n"
                .'	s = s.substring(s.indexOf("{") + 1, s.lastIndexOf("}"));'."\n"

                ."	 // skip event handler, if it is a duplicate\n"
                ."	if (! obj.evt_keys) {\n"
                ."		obj.evt_keys = new Array();\n"
                ."	}\n"
                ."	if (obj.evt_keys[s]) {\n"
                ."		return true;\n"
                ."	}\n"
                ."	obj.evt_keys[s] = true;\n"

                ."	// standard DOM\n"
                ."	if (obj.addEventListener) {\n"
                ."		obj.addEventListener(evt, fnc, (useCapture ? true : false));\n"
                ."		return true;\n"
                ."	}\n"

                ."	// IE\n"
                ."	if (obj.attachEvent) {\n"
                ."		return obj.attachEvent(onevent, fnc);\n"
                ."	}\n"

                ."	// old browser (e.g. NS4 or IE5Mac)\n"
                ."	if (! obj.evts) {\n"
                ."		obj.evts = new Array();\n"
                ."	}\n"
                ."	if (! obj.evts[onevent]) {\n"
                ."		obj.evts[onevent] = new Array();\n"
                ."	}\n"
                ."	var i = obj.evts[onevent].length;\n"
                ."	obj.evts[onevent][i] = fnc;\n"
                ."	obj[onevent] = new Function('var onevent=\"'+onevent+'\"; for (var i=0; i<this.evts[onevent].length; i++) this.evts[onevent][i]();');\n"
                ."}\n"
            ;
        }
        $onload_oneline = preg_replace('/\s+/s', ' ', $onload);
        $onload_oneline = preg_replace("/[\\']/", '\\\\$0', $onload_oneline);
        $str .= "quizportAttachEvent(window, 'load', '$onload_oneline');\n";
        if ($script_tags) {
            $str .= "//]]>\n"."</script>\n";
        }
        return $str;
    }

    function fix_onload_old($onload, $script_tags=false) {
        static $count = 0;
        $onload_temp  = 'onload_'.sprintf('%02d', (++$count));

        $onload_oneline = preg_replace('/\s+/s', ' ', $onload);
        $onload_nospace = str_replace(' ', '', $onload_oneline);

        $str = '';
        if ($script_tags) {
            $str .= "\n".'<script type="text/javascript">'."\n"."//<![CDATA[\n";
        }
        $str .= ''
            .'if (typeof(window.onload)=="function"){'."\n"
            .'	var s = onload.toString();'."\n"
            .'	s = s.replace(new RegExp("\\\\s+", "g"), "");'."\n"
            .'	if (s.indexOf("'.$onload_nospace.'")<0){'."\n"
            .'		window.'.$onload_temp.' = onload;'."\n"
            .'		window.onload = new Function("window.'.$onload_temp.'();"+"'.$onload_oneline.';");'."\n"
            .'	}'."\n"
            .'} else {'."\n"
            .'	window.onload = new Function("'.$onload_oneline.'");'."\n"
            .'}'."\n"
        ;
        if ($script_tags) {
            $str .= "//]]>\n"."</script>\n";
        }
        return $str;
    }

    function fix_mediafilter() {

        if (! $this->usemediafilter) {
            return false;
        }

        if (! quizport_load_mediafilter_filter($this->usemediafilter)) {
            return false;
        }
        $mediafilterclass = 'quizport_mediafilter_'.$this->usemediafilter;
        $mediafilter = new $mediafilterclass($this);

        $mediafilter->fix($this, 'headcontent');
        $mediafilter->fix($this, 'bodycontent');

        if ($mediafilter->js_inline) {
            // remove the internal </script><script ... > joins from the inline javascripts (js_inline)
            $search = '/(?:\/\/\]\]>\s*)?'.'<\/script>\s*<script type="text\/javascript">\s*'.'(?:\/\/<!\[CDATA\[[ \t]*[\r\n]*)?/is';
            $mediafilter->js_inline = preg_replace($search, "\n", $mediafilter->js_inline);

            // extract urls of deferred scripts from $mediafilter->js_external
            if (preg_match_all($this->tagpattern('script'), $mediafilter->js_external, $scripts, PREG_OFFSET_CAPTURE)) {
                $deferred_js = array();
                foreach (array_reverse($scripts[0]) as $script) {
                    // $script [0] => matched string, [1] => offset to start of matched string
                    $remove = false;
                    if (strpos($script[0], 'type="text/javascript"')) {
                        if (strpos($script[0], 'lib/ufo.js') && $this->usemoodletheme) {
                            // ufo.js not required because it will be included in the Moodle header
                            $remove = true;
                        } else if (strpos($script[0], 'defer="defer"')) {
                            if (preg_match('/src="(.*?)"/i', $script[0], $matches)) {
                                array_unshift($deferred_js, '"'.addslashes_js($matches[1]).'"');
                                $remove = true;
                            }
                        }
                    }
                    if ($remove) {
                        $mediafilter->js_external = substr_replace($mediafilter->js_external, '', $script[1], strlen($script[0]));
                    }
                }
                $deferred_js = implode(',', array_unique($deferred_js));
            } else {
                $deferred_js = '';
            }

            $functions = '';
            if (preg_match_all('/(?<=function )\w+/', $mediafilter->js_inline, $names)) {
                foreach ($names[0] as $name) {
                    list($start, $finish) = $this->locate_js_function($name, $mediafilter->js_inline, true);
                    if ($finish) {
                        $functions .= trim(substr($mediafilter->js_inline, $start, ($finish - $start)))."\n";
                        $mediafilter->js_inline = substr_replace($mediafilter->js_inline, '', $start, ($finish - $start));
                    }
                }
            }

            // put all the inline javascript into one single function called "quizport_mediafilter_loader()",
            // which also loads up any deferred js, and force this function to be run when the page has loaded
            $onload = 'quizport_mediafilter_loader()';
            $search = '/(\/\/<!\[CDATA\[)(.*)(\/\/\]\]>)/s';
            $replace = '\\1'."\n"
                .$functions
                .'function '.$onload.'{'
                .'\\2'
                ."\n"
                .'  // load deferred scripts'."\n"
                .'  var head = document.getElementsByTagName("head")[0];'."\n"
                .'  var urls = new Array('.$deferred_js.');'."\n"
                .'  for (var i=0; i<urls.length; i++) {'."\n"
                .'    var script = document.createElement("script");'."\n"
                .'    script.type = "text/javascript";'."\n"
                .'    script.src = urls[i];'."\n"
                .'    head.appendChild(script);'."\n"
                .'  }'."\n"
                .$this->fix_mediafilter_onload_extra()
                .'} // end function '.$onload."\n"
                ."\n"
                .$this->fix_onload($onload)
                .'\\3'
            ;
            $mediafilter->js_inline = preg_replace($search, $replace, $mediafilter->js_inline, 1);

            // append the inline javascripts to the end of the bodycontent
            $this->bodycontent .= $mediafilter->js_inline;
        }

        if ($mediafilter->js_external) {
            // append the external javascripts to the head content
            $this->headcontent .= $mediafilter->js_external;
        }
    }

    function fix_mediafilter_onload_extra() {
        return '';
    }

    function fix_relativeurls($str=null) {
        // elements of the regular expression which will search for the URLs
        $tagopen = '(?:(<)|(\\\\u003C)|(&lt;)|(&amp;#x003C;))'; // left angle bracket
        $tagclose = '(?(2)>|(?(3)\\\\u003E|(?(4)&gt;|(?(5)&amp;#x003E;))))'; //  right angle bracket (to match left angle bracket)

        $space = '\s+'; // at least one space
        $equals = '\s*=\s*'; // equals sign (+ white space)
        $anychar = '(?:[^>]*?)'; // any character

        $quoteopen = '("|\\\\"|&quot;|&amp;quot;'."|'|\\\\'|&apos;|&amp;apos;".')'; // open quote
        $quoteclose = '\\6'; //  close quote (to match open quote)

        // the replacement expression for the URLs
        $replace = '$this->convert_url_relative("'.$this->source->baseurl.'","'.$this->sourcefile.'","\\1","\\7","\\8")';

        // define which attributes of which HTML tags to search for URLs
        $tags = array(
            // tag   =>  attribute containing url
            'a'      => 'href',
            'area'   => 'href', // <area href="sun.htm" ... shape="..." coords="..." />
            'embed'  => 'src',
            'iframe' => 'src',
            'img'    => 'src',
            'input'  => 'src', // <input type="image" src="..." >
            'link'   => 'href',
            'object' => 'data',
            'param'  => 'value',
            'script' => 'src',
            'source' => 'src', // for HTML5
            '(?:table|th|td)'  => 'background'
        );

        // replace relative URLs in attributes of certain HTML tags
        foreach ($tags as $tag=>$attribute) {
            if ($tag=='param') {
                $url = '\S+?\.\S+?'; // must include a filename and have no spaces
            } else {
                $url = '.*?';
            }
            $search = "/($tagopen$tag$space$anychar$attribute$equals$quoteopen)($url)($quoteclose$anychar$tagclose)/ise";
            if (is_string($str)) {
                $str = preg_replace($search, $replace, $str);
            } else {
                $this->headcontent = preg_replace($search, $replace, $this->headcontent);
                $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);
            }
        }

        if (is_string($str)) {
            return $str;
        }

        // replace relative URLs in stylesheets
        $search = '/'.'(<style[^>]*>)'.'(.*?)'.'(<\/style>)'.'/ise';
        $replace = '"\\1".$this->convert_urls_stylesheets("'.$this->source->baseurl.'","'.$this->source->filepath.'","\\2")."\\3"';
        $this->headcontent = preg_replace($search, $replace, $this->headcontent);
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);

        // replace relative URLs in <a ... onclick="window.open('...')...">...</a>
        $search = '/'.'('.'onclick="'."window.open\('".')'."([^']*)".'('."'[^\)]*\);return false;".'")'.'/ise';
        $replace = '$this->convert_url_relative("'.$this->source->baseurl.'","'.$this->sourcefile.'","\\1","\\2","\\3")';
        $this->bodycontent = preg_replace($search, $replace, $this->bodycontent);
    }

    function convert_urls_stylesheets($baseurl, $sourcefile, $css, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $css = str_replace('\\'.$quote, $quote, $css);
        }
        $search = '/'.'(?<='.'url'.'\('.')'."(.+?)".'(?='.'\)'.')'.'/ise';
        $replace = '$this->convert_url("'.$baseurl.'","'.$sourcefile.'","\\1")';
        return preg_replace($search, $replace, $css);
    }

    function convert_url_relative($baseurl, $sourcefile, $opentag, $url, $closetag, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $opentag = str_replace('\\'.$quote, $quote, $opentag);
            $url = str_replace('\\'.$quote, $quote, $url);
            $closetag = str_replace('\\'.$quote, $quote, $closetag);
        }

        switch (true) {
            case preg_match('|^'.'\w+=[^&]+'.'('.'&((amp;#x0026;)?amp;)?'.'\w+=[^&]+)*'.'$|', $url):
                // catch <PARAM name="FlashVars" value="TheSound=soundfile.mp3">
                //  ampersands can appear as "&", "&amp;" or "&amp;#x0026;amp;"
                $query = $url;
                $url = '';
                $fragment = '';
                break;

            case preg_match('|^'.'([^?]*)'.'((?:\\?[^#]*)?)'.'((?:#.*)?)'.'$|', $url, $matches):
                // parse the $url into $matches
                //  [1] path
                //  [2] query string, if any
                //  [3] anchor fragment, if any
                $url = $matches[1];
                $query = $matches[2];
                $fragment = $matches[3];
                break;

            default:
                // there appears to be no query or fragment in this url
                $query = '';
                $fragment = '';
        } // end switch

        // convert the filepath part of the url
        if ($url) {
            $url = $this->convert_url($baseurl, $sourcefile, $url, false);
        }

        // convert urls, if any, in the query string
        if ($query) {
            $search = '/'.'(file|song_url|src|thesound|mp3)='."([^&]+)".'/ise';
            $replace = '"\\1=".$this->convert_url("'.$baseurl.'","'.$sourcefile.'","\\2")';
            $query = preg_replace($search, $replace, $query);
        }

        // return the reconstructed tag (with converted url)
        return $opentag.$url.$query.$fragment.$closetag;
    }
    function convert_url($baseurl, $sourcefile, $url, $quote="'") {
        global $CFG, $QUIZPORT;

        if ($quote) {
            // fix quotes escaped by preg_replace
            $url = str_replace('\\'.$quote, $quote, $url);
        }

        if ($CFG->slasharguments) {
            $file_php = 'file.php';
        } else {
            $file_php = 'file.php?file=';
        }

        // %domainfiles% is not needed, because the same effect can be achieved using simply "/my-file.html"
        //if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on') {
        //    $http = 'https';
        //} else {
        //    $http = 'http';
        //}
        //$url = str_replace('%domainfiles%', $http.'://'.$_SERVER[SERVER_NAME].'/', $url);

        // %quizfiles% is not needed, because this is the default behavior for a relative URL
        // $url = str_replace('%quizfiles%', $CFG->wwwroot.'/'.$baseurl, $url);

        // substitute %wwwroot%, %sitepage%, %coursepage%, %sitefiles% and %coursefiles%
        $replace_pairs = array(
            '%wwwroot%' => $CFG->wwwroot,
            '%sitepage%' => $CFG->wwwroot.'/course/view.php?id='.SITEID,
            '%sitefiles%' => $CFG->wwwroot.'/'.$file_php.'/'.SITEID,
            '%coursepage%' => $CFG->wwwroot.'/course/view.php?id='.$QUIZPORT->courserecord->id,
            '%coursefiles%' => $CFG->wwwroot.'/'.$file_php.'/'.$QUIZPORT->courserecord->id
        );
        $url = strtr($url, $replace_pairs);

        if (preg_match('/^(?:\/|(?:[a-zA-Z0-9]+:))/', $url)) {
            // no processing  - this is already an absolute url (http:, mailto:, javascript:, etc)
            return $url;
        }

        // get the subdirectory, $dir, of the quiz $sourcefile
        $dir = dirname($sourcefile);

        if ($baseurl=='' && preg_match('|^https?://|', $dir)) {
            $url = $dir.'/'.$url;
        } else {
            // remove leading "./" and "../"
            while (preg_match('|^(\.{1,2})/(.*)$|', $url, $matches)) {
                if ($matches[1]=='..') {
                    $dir = dirname($dir);
                }
                $url = $matches[2];
            }
            // add subdirectory, $dir, to $baseurl, if necessary
            if ($dir && $dir!='.') {
                $baseurl .= "/$dir";
            }
            // prefix $url with $baseurl
            $url = "$baseurl/$url";
        }

        return $url;
    } // end function : convert_url

    // functions to store responses returned from browser

    function store() {
        global $CFG, $DB, $QUIZPORT;

        if (empty($QUIZPORT->quizattempt)) {
            $QUIZPORT->create_quizattempt();
        }

        if ($QUIZPORT->quizattempt->userid != $QUIZPORT->userid) {
            return; // wrong userid - shouldn't happen !!
        }

        // update quiz attempt fields using incoming data
        $QUIZPORT->quizattempt->score = optional_param($this->scorefield, 0, PARAM_INT);
        $QUIZPORT->quizattempt->status = optional_param('status', 0, PARAM_INT);
        $QUIZPORT->quizattempt->redirect = optional_param('redirect', 0, PARAM_INT);
        $QUIZPORT->quizattempt->details = optional_param($this->detailsfield, '', PARAM_RAW);

        // time values, e.g. "2008-09-12 16:18:18 +0900",
        // need to be converted to numeric date stamps
        $timefields = array('starttime', 'endtime');
        foreach ($timefields as $timefield) {

            $QUIZPORT->quizattempt->$timefield = 0; // default
            if ($time = optional_param($timefield, '', PARAM_RAW)) {

                // make sure the timezone has a "+" sign
                // Note: sometimes it gets stripped (by optional_param?)
                $time = preg_replace('/(?<= )\d{4}$/', '+\\0', trim($time));

                // convert $time to numeric date stamp
                // PHP4 gives -1 on error, whereas PHP5 give false
                $time = strtotime($time);

                if ($time && $time>0) {
                    $QUIZPORT->quizattempt->$timefield = $time;
                }
            }
        }
        unset($timefields, $timefield, $time);

        // set finish times
        $QUIZPORT->quizattempt->timefinish = $QUIZPORT->time;
        $QUIZPORT->quizattempt->resumefinish = $QUIZPORT->time;

        // increment quiz attempt duration
        $startfield = $this->durationstartfield; // "starttime" or "resumestart"
        $finishfield = $this->durationfinishfield; // "endtime" or "resumefinish"
        $duration = ($QUIZPORT->quizattempt->$finishfield - $QUIZPORT->quizattempt->$startfield);
        if ($duration > 0) {
            $QUIZPORT->quizattempt->duration += $duration;
        }
        unset($duration, $startfield, $finishfield);

        // set clickreportid, (for click reporting)
        $QUIZPORT->quizattempt->clickreportid = $QUIZPORT->quizattempt->id;

        // check if there are any previous results stored for this attempt
        // this could happen if ...
        //     - the quiz has been resumed
        //     - clickreporting is enabled for this quiz
        if ($DB->get_field('quizport_quiz_attempts', 'timefinish', array('id'=>$QUIZPORT->quizattempt->id))) {
            if ($this->can_clickreport()) {
                // add quiz attempt record for each form submission
                // records are linked via the "clickreportid" field

                // update status in previous records in this clickreportid group
                $DB->set_field('quizport_quiz_attempts', 'status', $QUIZPORT->quizattempt->status, array('clickreportid'=>$QUIZPORT->quizattempt->clickreportid));

                // add new attempt record
                unset($QUIZPORT->quizattempt->id);
                if (! $QUIZPORT->quizattempt->id = $DB->insert_record('quizport_quiz_attempts', $QUIZPORT->quizattempt)) {
                    print_error('error_insertrecord', 'quizport', '', 'quizport_quiz_attempts');
                }

            } else {
                // remove previous responses for this attempt, if required
                // (N.B. this does NOT remove the attempt record, just the responses)
                $DB->delete_records('quizport_responses', array('attemptid'=>$QUIZPORT->quizattempt->id));
            }
        }

        // add details of this quiz attempt, if required
        // "quizport_storedetails" is set by administrator
        // Site Admin -> Modules -> Activities -> QuizPort
        if ($CFG->quizport_storedetails) {

            // delete/update/add the details record
            if ($DB->record_exists('quizport_details', array('attemptid'=>$QUIZPORT->quizattempt->id))) {
                $DB->set_field('quizport_details', 'details', $QUIZPORT->quizattempt->details, array('attemptid'=>$QUIZPORT->quizattempt->id));
            } else {
                $details = (object)array(
                    'attemptid' => $QUIZPORT->quizattempt->id,
                    'details' => $QUIZPORT->quizattempt->details
                );
                if (! $DB->insert_record('quizport_details', $details, false)) {
                    print_error('error_insertrecord', 'quizport', '', 'quizport_details');
                }
                unset($details);
            }
        }

        if ($CFG->majorrelease<=1.9) {
            // mimic stripslahes_safe() for Moodle 1.9 and earlier
            // slashes were added by magic_quotes_gpc (or lib/setup.php)
            // Note: from Moodle 2.0, input from client is not slashed
            if (ini_get_bool('magic_quotes_sybase')) {
                // only unescape single quotes
                $QUIZPORT->quizattempt->details = strtr($QUIZPORT->quizattempt->details, array("''"=>"'", ));
            } else {
                // unescape simple and double quotes and backslashes
                $QUIZPORT->quizattempt->details = strtr($QUIZPORT->quizattempt->details, array("\\'"=>"'", '\\"'=>'"', '\\\\'=>'\\'));
            }
        }

        // add details of this attempt
        $this->store_details($QUIZPORT->quizattempt);

        // update the attempt record
        if (! $DB->update_record('quizport_quiz_attempts', $QUIZPORT->quizattempt)) {
            print_error('error_updaterecord', 'quizport', '', 'quizport_quiz_attempts');
        }

        if ($QUIZPORT->quizattempt->status==QUIZPORT_STATUS_ABANDONED) {
            switch ($this->can_continue()) {
                case QUIZPORT_CONTINUE_ABANDONUNIT:
                    $QUIZPORT->unitgrade->status==QUIZPORT_STATUS_ABANDONED;
                    if (! $DB->set_field('quizport_unit_grades', 'status', QUIZPORT_STATUS_ABANDONED, array('id'=>$QUIZPORT->unitgrade->id))) {
                        print_error('error_updaterecord', 'quizport', '', 'quizport_unit_grades');
                    }
                case QUIZPORT_CONTINUE_RESTARTUNIT:
                    $QUIZPORT->unitattempt->status==QUIZPORT_STATUS_ABANDONED;
                    if (! $DB->set_field('quizport_unit_attempts', 'status', QUIZPORT_STATUS_ABANDONED, array('id'=>$QUIZPORT->unitattempt->id))) {
                        print_error('error_updaterecord', 'quizport', '', 'quizport_unit_attempts');
                    }
                case QUIZPORT_CONTINUE_RESTARTQUIZ:
                    $QUIZPORT->quizscore->status==QUIZPORT_STATUS_ABANDONED;
                    if (! $DB->set_field('quizport_quiz_scores', 'status', QUIZPORT_STATUS_ABANDONED, array('id'=>$QUIZPORT->quizscore->id))) {
                        print_error('error_updaterecord', 'quizport', '', 'quizport_quiz_scores');
                    }
                case QUIZPORT_CONTINUE_RESUMEQUIZ:
                    // $QUIZPORT->quizattempt has already been updated
                    // so we don't need to do anything here
            }
        }

        // regrade the quiz to take account of the latest quiz attempt score
        $QUIZPORT->regrade_quiz();
    }

    function pre_xmlize(&$old_string) {
        $new_string = '';
        $str_start = 0;
        while (($cdata_start = strpos($old_string, '<![CDATA[', $str_start)) && ($cdata_end = strpos($old_string, ']]>', $cdata_start))) {
            $cdata_end += 3;
            $new_string .= str_replace('&', '&amp;', substr($old_string, $str_start, $cdata_start-$str_start)).substr($old_string, $cdata_start, $cdata_end-$cdata_start);
            $str_start = $cdata_end;
        }
        $new_string .= str_replace('&', '&amp;', substr($old_string, $str_start));
        return $new_string;
    }

    function store_details(&$quizattempt) {

        // encode ampersands so that HTML entities are preserved in the XML parser
        // N.B. ampersands inside <![CDATA[ ]]> blocks do NOT need to be encoded
        // disabled 2008.11.20
        // $quizattempt->details = $this->pre_xmlize($quizattempt->details);

        // parse the attempt details as xml
        $details = xmlize($quizattempt->details);
        $question_number; // initially unset
        $question = false;
        $response  = false;

        $i = 0;
        while (isset($details[$this->xmlresultstag]['#']['fields']['0']['#']['field'][$i]['#'])) {

            // shortcut to field
            $field = &$details[$this->xmlresultstag]['#']['fields']['0']['#']['field'][$i]['#'];

            // extract field name and data
            if (isset($field['fieldname'][0]['#']) && is_string($field['fieldname'][0]['#'])) {
                $name = $field['fieldname'][0]['#'];
            } else {
                $name = '';
            }
            if (isset($field['fielddata'][0]['#']) && is_string($field['fielddata'][0]['#'])) {
                $data = $field['fielddata'][0]['#'];
            } else {
                $data = '';
            }

            // parse the field name into $matches
            //  [1] quiz type
            //  [2] attempt detail name
            if (preg_match('/^(\w+?)_(\w+)$/', $name, $matches)) {
                $quiztype = strtolower($matches[1]);
                $name = strtolower($matches[2]);

                // parse the attempt detail $name into $matches
                //  [1] question number
                //  [2] question detail name
                if (preg_match('/^q(\d+)_(\w+)$/', $name, $matches)) {
                    $num = $matches[1];
                    $name = strtolower($matches[2]);
                    // not needed Moodle 2.0 and later
                    // $data = addslashes($data);

                    // adjust JCross question numbers
                    if (preg_match('/^(across|down)(.*)$/', $name, $matches)) {
                        $num .= '_'.$matches[1]; // e.g. 01_across, 02_down
                        $name = $matches[2];
                        if (substr($name, 0, 1)=='_') {
                            $name = substr($name, 1); // remove leading '_'
                        }
                    }

                    if (isset($question_number) && $question_number==$num) {
                        // do nothing - this response is for the same question as the previous response
                    } else {
                        // store previous question / response (if any)
                        $this->add_response($quizattempt, $question, $response);

                        // initialize question object
                        $question = new stdClass();
                        $question->name = '';
                        $question->text = '';
                        $question->quizid = $quizattempt->quizid;

                        // initialize response object
                        $response = new stdClass();
                        $response->attemptid = $quizattempt->id;

                        // update question number
                        $question_number = $num;
                    }

                    // adjust field name and value, and set question type
                    // (may not be necessary one day)
                    // quizport_adjust_response_field($quiztype, $question, $num, $name, $data);

                    // add $data to the question/response details
                    switch ($name) {
                        case 'name':
                        case 'type':
                            $question->$name = $data;
                            break;
                        case 'text':
                            $question->$name = quizport_string_id($data);
                            break;

                        case 'correct':
                        case 'ignored':
                        case 'wrong':
                            $response->$name = quizport_string_ids($data);
                            break;

                        case 'score':
                        case 'weighting':
                        case 'hints':
                        case 'clues':
                        case 'checks':
                            $response->$name = intval($data);
                            break;
                    }

                } else { // attempt details

                    // adjust field name and value
                    //quizport_adjust_response_field($quiztype, $question, $num='', $name, $data);

                    // add $data to the attempt details
                    if ($name=='penalties') {
                        $quizattempt->$name = intval($data);
                    }
                }
            }

            $i++;
        } // end while

        // add the final question and response, if any
        $this->add_response($quizattempt, $question, $response);
    }
    function add_response(&$quizattempt, &$question, &$response) {
        global $CFG, $DB;

        if (! $question || ! $response || ! isset($question->name)) {
            // nothing to add
            return;
        }

        $loopcount = 1;
        $questionname = $question->name;

        // loop until we are able to add the response record
        $looping = true;
        while ($looping) {

            $question->md5key = md5($question->name);
            if (! $question->id = $DB->get_field('quizport_questions', 'id', array('quizid'=>$quizattempt->quizid, 'md5key'=>$question->md5key))) {
                // add question record
                if ($CFG->majorrelease<=1.9) {
                    $question->name = addslashes($question->name);
                }
                if (! $question->id = $DB->insert_record('quizport_questions', $question)) {
                    print_error('error_insertrecord', 'quizport', '', 'quizport_questions');
                }
            }

            if ($DB->record_exists('quizport_responses', array('attemptid'=>$quizattempt->id, 'questionid'=>$question->id))) {
                // there is already a response to this question for this attempt
                // probably because this quiz has two questions with the same text
                //  e.g. Which one of these answers is correct?

                // To workaround this, we create new question names
                //  e.g. Which one of these answers is correct? (2)
                // until we get a question name for which there is no response yet on this attempt

                $loopcount++;
                $question->name = "$questionname ($loopcount)";

                // This method fails to correctly identify questions in
                // quizzes which allow questions to be shuffled or omitted.
                // As yet, there is no workaround for such cases.

            } else {
                // no response found to this question in this attempt
                // so we can proceed
                $response->questionid = $question->id;

                // add response record
                if(! $response->id = $DB->insert_record('quizport_responses', $response)) {
                    print_error('error_insertrecord', 'quizport', '', 'quizport_responses');
                }
                $looping = false;
            }

        } // end while
    }

    function redirect($redirect) {
        // $redirect
        //     false OR "" : do nothing here
        //     true        : send 204 header and die
        //     string      : a URL to redirect to
        global $QUIZPORT;

        if (empty($redirect)) {
            return; // do nothing
        }

        if ($redirect===true) {
            // we need some check here to see if the user is trying to navigate away
            // from the page in which case we should just die and not send the header
            header("HTTP/1.0 204 No Response");
            // Note: don't use header("Status: 204"); because it can confuse PHP+FastCGI
            // http://moodle.org/mod/forum/discuss.php?d=108330
            die;
            // script will die here
        }

        if ($QUIZPORT->inpopup) {
            print ''
                .'<script type="text/javascript">'."\n"
                .'//<![CDATA['."\n"
                ."if (window.opener && !opener.closed) {\n"
                ."    opener.location = '$redirect';\n"
                ."}\n"
                .'//]]>'."\n"
                ."</script>\n"
            ;
            close_window();
            // script will die here
        } else {
            redirect($redirect);
            // script will die here
        }
    }

    function redirect_old() {
        global $CFG, $QUIZPORT;

        if ($this->delay3==QUIZPORT_DELAY3_DISABLE || $QUIZPORT->quizattempt->status==QUIZPORT_STATUS_INPROGRESS || $QUIZPORT->quizattempt->redirect==0) {
            // we need some check here to see if the user is trying to navigate away
            // from the page in which case we should just die and not send the header
            if (true) {
                // continue without reloading the page
                header("HTTP/1.0 204 No Response");
                // Note: don't use header("Status: 204"); because it can confuse PHP+FastCGI
                // http://moodle.org/mod/forum/discuss.php?d=108330
            }
            die;
            // script will die here
        }

        if ($QUIZPORT->quizattempt->status==QUIZPORT_STATUS_ABANDONED && ($this->can_continue()==QUIZPORT_CONTINUE_RESUMEQUIZ || $this->can_continue()==QUIZPORT_CONTINUE_RESTARTQUIZ)) {
            $url = $CFG->wwwroot.'/course/view.php?id='.$QUIZPORT->courserecord->id;
            if ($QUIZPORT->inpopup) {
                print ''
                    .'<script type="text/javascript">'."\n"
                    .'//<![CDATA['."\n"
                    ."if (window.opener && !opener.closed) {\n"
                    ."    opener.location = '$url';\n"
                    ."}\n"
                    .'//]]>'."\n"
                    ."</script>\n"
                ;
                close_window();
                // script will die here
            } else {
                redirect($url);
                // script will die here
            }
        }

        // continue to quizport/view.php (script will die here)
        $url = $QUIZPORT->format_url('view.php', 'coursemoduleid', array('coursemoduleid'=>$QUIZPORT->modulerecord->id, 'quizid'=>0, 'qnumber'=>0, 'quizattemptid'=>0, 'quizscoreid'=>0));

        // watch out! FF behaves strangely @header('Location: '.$url);
        // so we don't use standard Moodle redirect($url)
        // redirect(str_replace('&amp;', '&', $url));

        // the following line is good enough to redirect modern browsers
        echo '<meta http-equiv="refresh" content="0; url='.$url.'" />'."\n";

        // older browsers might need the following
        echo '<script type="text/javascript">'."\n";
        echo '//<![CDATA['."\n";
        echo "location.replace('".str_replace('&amp;', '&', $url)."');\n";
        echo '//]]>'."\n";
        echo '</script>'."\n";
        die;
    }

    function review() {
        global $DB, $QUIZPORT;

        if (has_capability('mod/quizport:viewreports', $QUIZPORT->modulecontext)) {
            // teacher
        } else if ($this->timeclose && $this->timeclose > $QUIZPORT->time) {
            // student: quiz is closed
        } else {
            // student: quiz is still open
        }

        // set $noreview flag if user cannot review quiz attempt(s) right now
        // set $reviewoptions to relevant part of $this->reviewoptions
        $noreview = false;
        $reviewoptions = 0;
        if (has_capability('mod/quizport:viewreports', $QUIZPORT->modulecontext)) {
            // teacher can always review (anybody's) quiz attempts
            $reviewoptions = (QUIZPORT_REVIEW_AFTERATTEMPT | QUIZPORT_REVIEW_AFTERCLOSE);
        } else if ($this->timeclose && $this->timeclose > $QUIZPORT->time) {
            // quiz is closed
            if ($this->reviewoptions & QUIZPORT_REVIEW_AFTERCLOSE) {
                // user can review quiz attempt after quiz closes
                $reviewoptions = ($this->reviewoptions & QUIZPORT_REVIEW_AFTERCLOSE);
            } else if ($this->reviewoptions & QUIZPORT_REVIEW_AFTERATTEMPT) {
                $noreview = get_string('noreviewbeforeclose', 'quizport', userdate($this->timeclose));
            } else {
                $noreview = get_string('noreview', 'quizport');
            }
        } else {
            // quiz is still open
            if ($this->reviewoptions & QUIZPORT_REVIEW_AFTERATTEMPT) {
                // user can review quiz attempt while quiz is open
                $reviewoptions = ($this->reviewoptions & QUIZPORT_REVIEW_AFTERATTEMPT);
            } else if ($this->reviewoptions & QUIZPORT_REVIEW_AFTERCLOSE) {
                $noreview = get_string('noreviewafterclose', 'quizport');
            } else {
                $noreview = get_string('noreview', 'quizport');
            }
        }
        if ($noreview) {
            print_box($noreview, 'generalbox', 'centeredboxtable');
            return;
        }

        // if necessary, remove score and weighting fields
        if (! ($reviewoptions & QUIZPORT_REVIEW_SCORES)) {
            $this->response_num_fields = preg_grep('/^score|weighting$/', $this->response_num_fields, PREG_GREP_INVERT);
        }

        // if necessary, remove reponses fields
        if (! ($reviewoptions & QUIZPORT_REVIEW_RESPONSES)) {
            $this->response_text_fields = array();
        }

        // set flag to remove, if necessary, labels that show whether responses are correct or not
        if (! ($reviewoptions & QUIZPORT_REVIEW_ANSWERS)) {
            $neutralize_text_fields = true;
        } else {
            $neutralize_text_fields = false;
        }

        $table = new quizport_flexible_table(QUIZPORT_PAGEID);

        $table->set_attribute('id', 'review');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        $columns = array();
        foreach ($this->response_num_fields as $field) {
            $columns[] = $field.'txt';
            $columns[] = $field;
        }
        $table->define_columns($columns);

        $table->setup();

        // get questions and responses relevant to this quiz attempt
        $questions = $DB->get_records_select('quizport_questions', 'quizid='.$QUIZPORT->quizattempt->quizid);
        $responses = $DB->get_records_select('quizport_responses', 'attemptid='.$QUIZPORT->quizattempt->id);

        // cache number of columns
        $countcolumns = count($columns);

        if ($questions && $responses) {
            foreach ($responses as $response) {
                if (empty($questions[$response->questionid])) {
                    debugging("Invalid questionid, $response->questionid, in quizport_response record, $response->id", DEBUG_DEVELOPER);
                    continue;
                }

                // add a separator, if required
                if (! empty($table->data)) {
                    $table->add_data(null);
                }

                $text = quizport_get_question_name($questions[$response->questionid]);
                if ($text!=='') {
                    $table->add_data(array(array('text'=>$text, 'class'=>'questiontext', 'colspan'=>$countcolumns)));
                }

                $text_colspan = $countcolumns - 1;

                $neutral_text = '';
                foreach ($this->response_text_fields as $field) {
                    $text = quizport_strings($response->$field);
                    if ($text==='') {
                        continue;
                    }
                    if ($neutralize_text_fields) {
                        $neutral_text .= ($neutral_text ? ',' : '').$text;
                    } else {
                        $table->add_data(array(get_string($field, 'quizport'), array('text'=>$text, 'colspan'=>$text_colspan)));
                    }
                }
                if ($neutral_text) {
                    $table->add_data(array(get_string('responses', 'quiz'), array('text'=>$neutral_text, 'colspan'=>$text_colspan)));
                }

                $row = array();
                foreach ($this->response_num_fields as $field) {
                    array_push($row, get_string($field, 'quizport'), $response->$field);
                }
                $table->add_data($row);
            }
        } else {
            $text = get_string('noresponses', 'quizport');
            $table->add_data(array(array('text'=>$text, 'class'=>'noresponses', 'colspan'=>$countcolumns)));
        }

        $table->print_html();
    }

    function set_reports() {
        $this->reports['overview'] = array('reportoverview', 'quiz');
        $this->reports['simple'] = array('reportsimplestat', 'quiz');
        $this->reports['detailed'] = array('reportfullstat', 'quiz');
        if ($this->can_clickreport()) {
            $this->reports['click'] = array('reportclick', 'quizport');
        }
    }

    function get_reports() {
        if (count($this->reports)) {
            return $this->reports;
        } else {
            return false;
        }
    }

/*
    function report_overview() {
        // standard overview report is "display_table_quizattempts()" method
        // in "mod/quizport/report.class.php"
    }
*/

    function report_simple() {
        global $CFG, $DB, $QUIZPORT;

        list($userfilter, $attemptfilter, $unumberfilter, $params)
            = $QUIZPORT->display_user_selector($QUIZPORT->quizscore, 'qqa');

        $fields = ''
            .'qqa.*, u.firstname, u.lastname, u.picture'
        ;
        $tables = ''
            .'{quizport_quiz_attempts} qqa JOIN ('
                .'SELECT id, firstname, lastname, picture '
                .'FROM {user}'
            .') u ON u.id=qqa.userid'
        ;
        $select = 'qqa.quizid='.$QUIZPORT->quizid.' AND '.$userfilter.$attemptfilter.$unumberfilter;

        if (! $userfilter || ! $quizattempts = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select ORDER BY u.lastname,u.firstname,qqa.unumber,qqa.qnumber", $params)) {
            print_box(get_string('noresultsfound', 'quizport'), 'generalbox', 'centeredboxtable');
            return false;
        }

        $users = array();
        $quizattemptids = array_keys($quizattempts);
        foreach ($quizattemptids as $id) {
            $quizattempt->questionscores = array();
            $userid = $quizattempts[$id]->userid;

            if (! isset($users[$userid])) {
                $users[$userid] = (object)array(
                    'firstname' => $quizattempts[$id]->firstname,
                    'lastname' => $quizattempts[$id]->lastname,
                    'picture' => $quizattempts[$id]->picture,
                    'quizattempts' => array()
                );
            }
            unset(
                $quizattempts[$id]->userid, $quizattempts[$id]->picture,
                $quizattempts[$id]->firstname, $quizattempts[$id]->lastname
            );
            $users[$userid]->quizattempts[$id] = &$quizattempts[$id];
        }

        $fields = 'id,attemptid,questionid,score';
        $select = 'attemptid IN ('.implode(',', $quizattemptids).')';
        if (! $responses = $DB->get_records_select('quizport_responses', $select, null, '', $fields)) {
            print_box(get_string('noresultsfound', 'quizport'), 'generalbox', 'centeredboxtable');
            return false;
        }

        $questionids = array();
        foreach (array_keys($responses) as $id) {
            $response = &$responses[$id];

            $questionid = $response->questionid;
            $questionids[$questionid] = true;

            $attemptid = $response->attemptid;
            $quizattempts[$attemptid]->questionscores[$questionid] = $response->score;
        }
        $questionids = array_keys($questionids);

        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->is_collapsible = true;
        $table->set_attribute('id', 'simple');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        $columns = array_merge($QUIZPORT->usercolumns, array('quizattempt'), $QUIZPORT->quizattemptcolumns);
        foreach ($questionids as $i=>$id) {
            $columns[] = 'q'.$i;
        }
        if ($QUIZPORT->showselectcolumn) {
            $columns[] = 'select';
        }

        $table->define_columns($columns);
        $table->define_headers($QUIZPORT->table_headers($columns));

        $table->column_class('picture', 'userinfo');
        $table->column_class('fullname', 'userinfo');
        if ($QUIZPORT->showselectcolumn) {
            $table->column_class('select', 'select');
        }

        $table->setup();
        $dateformat = get_string('strftimerecent');

        $oddeven = 1;
        foreach ($users as $userid=>$user) {
            $oddeven = $oddeven ? 0 : 1;
            $class = 'r'.$oddeven;

            $print_user = true;
            foreach ($user->quizattempts as $quizattemptid=>$quizattempt) {

                // start the table row
                $row = array();

                // add user details
                if ($print_user) {
                    $print_user = false;
                    $rowspan = count($user->quizattempts);
                    $picture = print_user_picture($userid, $QUIZPORT->courserecord->id, $user->picture, false, true);
                    $fullname = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'&amp;course='.$QUIZPORT->courserecord->id.'">'.fullname($user).'</a>';
                    array_push($row,
                        array('text'=>$picture, 'rowspan'=>$rowspan, 'class'=>$class),
                        array('text'=>$fullname, 'rowspan'=>$rowspan, 'class'=>$class)
                    );
                } else {
                    array_push($row, '',''); // these cells will be skipped
                }

                // add quiz attempt number
                $quizattempthref = $QUIZPORT->format_url('report.php', '', array('quizattemptid'=>$quizattempt->id));
                array_push($row,
                    array('text'=>'<a href="'.$quizattempthref.'">'.$quizattempt->qnumber.'</a>', 'class'=>$class)
                );

                // add quiz attempt details
                $timemodified = max($quizattempt->starttime, $quizattempt->endtime, $quizattempt->timestart, $quizattempt->timefinish);
                array_push($row,
                    array('text'=>'<a href="'.$quizattempthref.'">'.$quizattempt->score.'</a>', 'class'=>$class),
                    array('text'=>quizport_format_status($quizattempt->status), 'class'=>$class),
                    array('text'=>userdate($timemodified, $dateformat), 'class'=>$class),
                    array('text'=>quizport_format_time($quizattempt->duration), 'class'=>$class)
                );

                // add question scores
                foreach ($questionids as $questionid) {
                    if (isset($quizattempt->questionscores[$questionid])) {
                        $text = $quizattempt->questionscores[$questionid];
                    } else {
                        $text = '-';
                    }
                    $row[] = array('text'=>$text, 'class'=>$class);
                }

                // add select column, if required
                if ($QUIZPORT->showselectcolumn) {
                    array_push($row, array('class'=>$class));
                }

                $table->add_data($row, array('class'=>''));
            }
        }
        if (! empty($table->data)) {
            $table->print_html();
        }
    }

    function report_detailed() {
        global $CFG, $DB, $QUIZPORT;

        list($userfilter, $attemptfilter, $unumberfilter, $params)
            = $QUIZPORT->display_user_selector($QUIZPORT->quizscore, 'qqa');

        $fields = ''
            .'qqa.*, u.firstname, u.lastname, u.picture'
        ;
        $tables = ''
            .'{quizport_quiz_attempts} qqa JOIN ('
                .'SELECT id, firstname, lastname, picture '
                .'FROM {user}'
            .') u ON u.id=qqa.userid'
        ;
        $select = 'qqa.quizid='.$QUIZPORT->quizid.' AND '.$userfilter.$attemptfilter.$unumberfilter;

        if (! $userfilter || ! $quizattempts = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select ORDER BY u.lastname,u.firstname,qqa.unumber,qqa.qnumber", $params)) {
            print_box(get_string('noresultsfound', 'quizport'), 'generalbox', 'centeredboxtable');
            return false;
        }

        $users = array();

        $quizattemptids = array_keys($quizattempts);
        foreach ($quizattemptids as $id) {
            $quizattempts[$id]->responses = array();
            $userid = $quizattempts[$id]->userid;

            if (! isset($users[$userid])) {
                $users[$userid] = (object)array(
                    'firstname' => $quizattempts[$id]->firstname,
                    'lastname' => $quizattempts[$id]->lastname,
                    'picture' => $quizattempts[$id]->picture,
                    'quizattempts' => array()
                );
            }
            unset(
                $quizattempts[$id]->userid, $quizattempts[$id]->picture,
                $quizattempts[$id]->firstname, $quizattempts[$id]->lastname
            );
            $users[$userid]->quizattempts[$id] = &$quizattempts[$id];
        }

        $select = 'attemptid IN ('.implode(',', $quizattemptids).')';
        if (! $responses = $DB->get_records_select('quizport_responses', $select)) {
            print_box(get_string('noresultsfound', 'quizport'), 'generalbox', 'centeredboxtable');
            return false;
        }

        $questions = array();
        foreach (array_keys($responses) as $id) {
            $attemptid = $responses[$id]->attemptid;
            $questionid = $responses[$id]->questionid;

            $quizattempts[$attemptid]->responses[$questionid] = &$responses[$id];

            if (empty($questions[$questionid])) {
                $questions[$questionid] = array();
            }
            $questions[$questionid][$attemptid] = true;
        }
        $questionids = array_keys($questions);

        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->is_collapsible = true;
        $table->set_attribute('id', 'detailed');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        $columns = array_merge($QUIZPORT->usercolumns, array('quizattempt'), $QUIZPORT->quizattemptcolumns);
        foreach ($questionids as $i=>$id) {
            $columns[] = 'q'.$i;
        }
        if ($QUIZPORT->showselectcolumn) {
            $columns[] = 'select';
        }

        $table->define_columns($columns);
        $table->define_headers($QUIZPORT->table_headers($columns));

        $table->column_class('picture', 'userinfo');
        $table->column_class('fullname', 'userinfo');
        if ($QUIZPORT->showselectcolumn) {
            $table->column_class('select', 'select');
        }

        $table->setup();
        $dateformat = get_string('strftimerecent');

        $oddeven = 1;
        foreach ($users as $userid=>$user) {
            $oddeven = $oddeven ? 0 : 1;
            $class = 'r'.$oddeven;

            $print_user = true;
            foreach ($user->quizattempts as $quizattemptid=>$quizattempt) {

                // start the table row
                $row = array();

                // add user details
                if ($print_user) {
                    $print_user = false;
                    $rowspan = count($user->quizattempts);
                    $picture = print_user_picture($userid, $QUIZPORT->courserecord->id, $user->picture, false, true);
                    $fullname = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'&amp;course='.$QUIZPORT->courserecord->id.'">'.fullname($user).'</a>';
                    array_push($row,
                        array('text'=>$picture, 'rowspan'=>$rowspan, 'class'=>$class),
                        array('text'=>$fullname, 'rowspan'=>$rowspan, 'class'=>$class)
                    );
                } else {
                    array_push($row, '',''); // these cells will be skipped
                }

                // add quiz attempt number
                $quizattempthref = $QUIZPORT->format_url('report.php', '', array('quizattemptid'=>$quizattempt->id));
                array_push($row,
                    array('text'=>'<a href="'.$quizattempthref.'">'.$quizattempt->qnumber.'</a>', 'class'=>$class)
                );

                // add quiz attempt details
                $timemodified = max($quizattempt->starttime, $quizattempt->endtime, $quizattempt->timestart, $quizattempt->timefinish);
                array_push($row,
                    array('text'=>'<a href="'.$quizattempthref.'">'.$quizattempt->score.'</a>', 'class'=>$class),
                    array('text'=>quizport_format_status($quizattempt->status), 'class'=>$class),
                    array('text'=>userdate($timemodified, $dateformat), 'class'=>$class),
                    array('text'=>quizport_format_time($quizattempt->duration), 'class'=>$class)
                );

                // add response details
                foreach ($questionids as $questionid) {
                    if (isset($quizattempt->responses[$questionid])) {
                        $response = &$quizattempt->responses[$questionid];

                        $strings = array();
                        foreach ($this->detailed_string_fields as $field) {
                            if ($value = quizport_strings($response->$field)) {
                                $strings[] = '<span class="'.$field.'">'.$value.'</span>';
                            }
                        }
                        $numbers = array();
                        if ($field = $this->detailed_score_field) {
                            if (is_numeric($response->$field)) {
                                $numbers[] = '<span class="'.$field.'">'.$response->$field.'%</span>';
                            }
                        }
                        $clicks = array();
                        foreach ($this->detailed_number_fields as $field) {
                            if (empty($response->$field)) {
                                $clicks[] = '<span class="'.$field.'">0</span>';
                            } else {
                                $clicks[] = '<span class="'.$field.'">'.$response->$field.'</span>';
                            }
                        }
                        if (count($clicks)) {
                            $numbers[] = '<span class="clicks">('.implode(', ', $clicks).')</span>';
                        }
                        if (count($numbers)) {
                            $strings[] = '<div class="numbers">'.implode(' ', $numbers).'</div>';
                        }
                        if (count($strings)) {
                            $text = '<div class="strings">'.implode('<br />', $strings).'</div>';
                        } else {
                            $text = '-';
                        }
                        unset($clicks, $numbers, $strings);
                    } else {
                        $text = '-';
                    }
                    $row[] = array('text'=>$text, 'class'=>$class);
                }

                // add select column, if required
                if ($QUIZPORT->showselectcolumn) {
                    array_push($row, array('class'=>$class));
                }

                $table->add_data($row, array('class'=>''));
            }
        }

        if (! empty($table->data)) {
            $table->print_html();
        }
        unset($table);

		$fields = array('correct', 'wrong', 'ignored', 'hints', 'clues', 'checks', 'weighting');
		$string_fields = array('correct', 'wrong', 'ignored');

		$q = array(); // statistics about the $q(uestions)
		$f = array(); // statistics about the $f(ields)

		foreach ($questions as $questionid=>$attempts) {
            $scores = array();
            foreach (array_keys($attempts) as $attemptid) {
                $scores[] = $quizattempts[$attemptid]->score;
            }
            asort($scores);
            $count = count($scores);
            switch ($count) {
                case 0:
                    $lo_score = 0;
                    $hi_score = 0;
                    break;
                case 1:
                    $lo_score = 0;
                    $hi_score = $scores[0];
                    break;
                default:
                    $lo_score = $scores[round($count*1/3)];
                    $hi_score = $scores[round($count*2/3)];
                    break;
            }

            // loop through attempts which include this question
            foreach (array_keys($attempts) as $attemptid) {

                // reference to current attempt
				$is_hi_score = ($quizattempts[$attemptid]->score >= $hi_score);
				$is_lo_score = ($quizattempts[$attemptid]->score <  $lo_score);

                // reference to response to current question
				$response = &$quizattempts[$attemptid]->responses[$questionid];

				// update statistics for fields in this response
				foreach($fields as $field) {
					if (! isset($q[$questionid])) {
						$q[$questionid] = array();
					}
					if (! isset($f[$field])) {
						$f[$field] = array('count' => 0);
					}
					if (! isset($q[$questionid][$field])) {
						$q[$questionid][$field] = array('count' => 0);
					}
					$values = explode(',', $response->$field);
					$values = array_unique($values);
					foreach($values as $value) {
						// $value should be an integer (string_id or count)
						if (is_numeric($value)) {
							$f[$field]['count']++;
							if (! isset($q[$questionid][$field][$value])) {
								$q[$questionid][$field][$value] = 0;
							}
							$q[$questionid][$field]['count']++;
							$q[$questionid][$field][$value]++;
						}
					}
				} // end foreach $field

				// initialize counters for this question, if necessary
				if (! isset($q[$questionid]['count'])) {
					$q[$questionid]['count'] = array('hi'=>0, 'lo'=>0, 'correct'=>0, 'total'=>0, 'sum'=>0);
				}

				// increment counters
				$q[$questionid]['count']['sum'] += $response->score;
				$q[$questionid]['count']['total']++;
				if ($response->score==100) {
					$q[$questionid]['count']['correct']++;
					if ($is_hi_score) {
						$q[$questionid]['count']['hi']++;
					} else if ($is_lo_score) {
						$q[$questionid]['count']['lo']++;
					}
                }
            } // end foreach $attempts

            // sort fields so that most common comes first
            foreach($fields as $field) {
                arsort($q[$questionid][$field]);
            }

        } // end foreach $questions

		if (count($q)==0) {
            return false;
        }


        // table properties
        $table = new quizport_flexible_table(QUIZPORT_PAGEID); // mod-quizport-index

        $table->is_collapsible = true;
        $table->set_attribute('id', 'itemanalysis');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->set_attribute('cellpadding', '4');

        $columns = array('field');
        foreach ($questionids as $i=>$id) {
            $columns[] = 'q'.$i;
        }
        $table->define_columns($columns);
        $table->define_headers($QUIZPORT->table_headers($columns));

        $caption = get_string('itemanal', 'quiz');
        $table->set_caption(
            $caption.helpbutton('itemanalysis', $caption, 'quizport', true, false, '', true)
        );

        $table->setup();
        $oddeven = 1;

        // add question texts
        $select = 'id IN ('.implode(',', $questionids).')';
        if ($questions = $DB->get_records_select('quizport_questions', $select)) {
            $oddeven = ($oddeven ? 0 : 1);
            $class = 'r'.$oddeven;
            $row = array(array('text'=>get_string('question'), 'class'=>$class));
            foreach ($questionids as $id) {
                if (empty($questions[$id])) {
                    $text = ''; // shouldn't happen !!
                } else {
                    $text = quizport_get_question_name($questions[$id]);
                }
                $row[] = array('text'=>$text, 'class'=>$class);
            }
            $table->add_data($row, array('class'=>''));
        }

        // each $field is a row
        foreach ($fields as $field) {

            if ($f[$field]['count']==0) {
                continue;
            }

            $oddeven = $oddeven ? 0 : 1;
            $rowclass = 'r'.$oddeven;

            if (in_array($field, $string_fields)===false) {
                $is_string_field = false;
            } else {
                $is_string_field = true;
            }

            // leftmost column is the heading
            $row = array(
                array('text'=>get_string($field, 'quizport'), 'class'=>$rowclass)
            );

            // add statistics about this field for each question
            foreach ($questionids as $id) {
                $total = $q[$id]['count']['total'];
                $text = array();
                foreach ($q[$id][$field] as $value => $count) {
                    if ($value==='count' || $count==0) {
                        continue;
                    }
                    if ($total) {
                        $percent = round(100 * $count / $total);
                    } else {
                        $percent = 0;
                    }
                    if ($is_string_field) {
                        $value = quizport_strings($value);
                        $divclass = 'strings';
                    } else {
                        $divclass = 'numbers';
                    }
                    $text[] = '<div class="'.$divclass.'"><span class="percent">'.$percent.'%</span> '.$value.'</div>';
                }

                if (! $text = implode('', $text)) {
                    $text = '-';
                }

                // add $question cell to this row
                $row[] = array('text'=>$text, 'class'=>$rowclass.' '.$field);

            } // end foreach $questionids

            // add $field row to table
            $table->add_data($row, array('class'=>''));

        }// end foreach $fields

        $max_discrim = 10;

        $stats = array(
            'average' => get_string('average', 'quizport'),
            'percent' => get_string('percentcorrect', 'quiz'),
            'discrim' => get_string('discrimination', 'quiz')
        );

        // add separator, if there are any statistics rows
        if (count($stats)) {
            $oddeven = ($oddeven ? 0 : 1);
            $class = 'r'.$oddeven;
            $table->add_data(null);
        }

        foreach ($stats as $name=>$str) {
            $oddeven = ($oddeven ? 0 : 1);
            $class = 'r'.$oddeven;

            $row = array(array('text'=>$str, 'class'=>$class));

            foreach ($questionids as $id) {
                $text = '-';
                switch ($name) {
                    case 'average':
                        if ($q[$id]['count']['total']) {
                            $text = round($q[$id]['count']['sum'] / $q[$id]['count']['total']).'%';
                        }
                        break;
                    case 'percent':
                        if ($q[$id]['count']['total']) {
                            $text = round(100 * $q[$id]['count']['correct'] / $q[$id]['count']['total']).'%';
                            $text .= ' ('.$q[$id]['count']['correct'].'/'.$q[$id]['count']['total'].')';
                        }
                        break;
                    case 'discrim':
                        if ($q[$id]['count']['lo']) {
                            $text = min($max_discrim, round($q[$id]['count']['hi'] / $q[$id]['count']['lo'], 1));
                        } else {
                            $text = $q[$id]['count']['hi'] ? $max_discrim : 0;
                        }
                        $text .= ' ('.$q[$id]['count']['hi'].'/'.$q[$id]['count']['lo'].')';
                        break;
                }
                $row[] = array('text'=>$text, 'class'=>$class);
            }
            $table->add_data($row, array('class'=>''));
        }

        if (! empty($table->data)) {
            $table->print_html();
        }
    }
}

function quizport_get_module_info($info) {
    global $CFG;

    static $module;
    if (! isset($module)) {
        // set up $module (first time only)
        require($CFG->dirroot.'/mod/quizport/version.php');
    }

    if (isset($module->$info)) {
        return $module->$info;
    } else {
        return '';
    }
}

function quizport_load_mediafilter_filter($classname) {
    global $CFG;
    $path = $CFG->dirroot.'/mod/quizport/mediafilter/'.$classname.'/class.php';

    // check the filter exists
    if (! file_exists($path)) {
        debugging('QuizPort mediafilter class is not accessible: '.$classname, DEBUG_DEVELOPER);
        return false;
    }

    return require_once($path);
}
?>
