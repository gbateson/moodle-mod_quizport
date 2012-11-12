<?php
/**
 * This old function used to implement boxes using tables.  Now it uses a DIV, but the old
 * parameters remain.  If possible, $align, $width and $color should not be defined at all.
 * Even better, please use print_box_start() in weblib.php
 *
 * @param string $align alignment of the box, not the text (default center, left, right).   DEPRECATED
 * @param string $width width of the box, including % units, for example '100%'.            DEPRECATED
 * @param string $color background colour of the box, for example '#eee'.                   DEPRECATED
 * @param int $padding padding in pixels, specified without units.                          OBSOLETE
 * @param string $class space-separated class names.
 * @param string $id space-separated id names.
 * @param boolean $return return as string or just print it
 * @return string|void Depending on $return
 */
function print_simple_box_start($align='', $width='', $color='', $padding=5, $class='generalbox', $id='', $return=false) {
    debugging('print_simple_box(_start/_end) is deprecated. Please use $OUTPUT->box(_start/_end) instead', DEBUG_DEVELOPER);

    $output = '';

    $divclasses = 'box '.$class.' '.$class.'content';
    $divstyles  = '';

    if ($align) {
        $divclasses .= ' boxalign'.$align;    // Implement alignment using a class
    }
    if ($width) {    // Hopefully we can eliminate these in calls to this function (inline styles are bad)
        if (substr($width, -1, 1) == '%') {    // Width is a % value
            $width = (int) substr($width, 0, -1);    // Extract just the number
            if ($width < 40) {
                $divclasses .= ' boxwidthnarrow';    // Approx 30% depending on theme
            } else if ($width > 60) {
                $divclasses .= ' boxwidthwide';      // Approx 80% depending on theme
            } else {
                $divclasses .= ' boxwidthnormal';    // Approx 50% depending on theme
            }
        } else {
            $divstyles  .= ' width:'.$width.';';     // Last resort
        }
    }
    if ($color) {    // Hopefully we can eliminate these in calls to this function (inline styles are bad)
        $divstyles  .= ' background:'.$color.';';
    }
    if ($divstyles) {
        $divstyles = ' style="'.$divstyles.'"';
    }

    if ($id) {
        $id = ' id="'.$id.'"';
    }

    $output .= '<div'.$id.$divstyles.' class="'.$divclasses.'">';

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}


/**
 * Print the end portion of a standard themed box.
 * Preferably just use print_box_end() in weblib.php
 *
 * @param boolean $return return as string or just print it
 * @return string|void Depending on $return
 */
function print_simple_box_end($return=false) {
    $output = '</div>';
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * @deprecated use $PAGE->theme->name instead.
 * @return string the name of the current theme.
 */
function current_theme() {
    global $PAGE;
    // TODO, uncomment this once we have eliminated all references to current_theme in core code.
    // debugging('current_theme is deprecated, use $PAGE->theme->name instead', DEBUG_DEVELOPER);
    return $PAGE->theme->name;
}

/**
 * @todo Remove this deprecated function when no longer used
 * @deprecated since Moodle 2.0 - use $PAGE->pagetype instead of the .
 *
 * @param string $getid used to return $PAGE->pagetype.
 * @param string $getclass used to return $PAGE->legacyclass.
 */
function page_id_and_class(&$getid, &$getclass) {
    global $PAGE;
    debugging('Call to deprecated function page_id_and_class. Please use $PAGE->pagetype instead.', DEBUG_DEVELOPER);
    $getid = $PAGE->pagetype;
    $getclass = $PAGE->legacyclass;
}

/**
 * Prints text in a format for use in headings.
 *
 * @deprecated
 * @param string $text The text to be displayed
 * @param string $deprecated No longer used. (Use to do alignment.)
 * @param int $size The size to set the font for text display.
 * @param string $class
 * @param bool $return If set to true output is returned rather than echoed, default false
 * @param string $id The id to use in the element
 * @return string|void String if return=true nothing otherwise
 */
function print_heading($text, $deprecated = '', $size = 2, $class = 'main', $return = false, $id = '') {
    global $OUTPUT;
    debugging('print_heading() has been deprecated. Please change your code to use $OUTPUT->heading().');
    if (!empty($deprecated)) {
        debugging('Use of deprecated align attribute of print_heading. ' .
                'Please do not specify styling in PHP code like that.', DEBUG_DEVELOPER);
    }
    $output = $OUTPUT->heading($text, $size, $class, $id);
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Print a message in a standard themed box.
 * Replaces print_simple_box (see deprecatedlib.php)
 *
 * @deprecated
 * @param string $message, the content of the box
 * @param string $classes, space-separated class names.
 * @param string $ids
 * @param boolean $return, return as string or just print it
 * @return string|void mixed string or void
 */
function print_box($message, $classes='generalbox', $ids='', $return=false) {
    global $OUTPUT;
    debugging('print_box() has been deprecated. Please change your code to use $OUTPUT->box().');
    $output = $OUTPUT->box($message, $classes, $ids);
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Starts a box using divs
 * Replaces print_simple_box_start (see deprecatedlib.php)
 *
 * @deprecated
 * @param string $classes, space-separated class names.
 * @param string $ids
 * @param boolean $return, return as string or just print it
 * @return string|void  string or void
 */
function print_box_start($classes='generalbox', $ids='', $return=false) {
    global $OUTPUT;
    debugging('print_box_start() has been deprecated. Please change your code to use $OUTPUT->box_start().');
    $output = $OUTPUT->box_start($classes, $ids);
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Simple function to end a box (see above)
 * Replaces print_simple_box_end (see deprecatedlib.php)
 *
 * @deprecated
 * @param boolean $return, return as string or just print it
 * @return string|void Depending on value of return
 */
function print_box_end($return=false) {
    global $OUTPUT;
    debugging('print_box_end() has been deprecated. Please change your code to use $OUTPUT->box_end().');
    $output = $OUTPUT->box_end();
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Starts a container using divs
 *
 * @deprecated
 * @param boolean $clearfix clear both sides
 * @param string $classes, space-separated class names.
 * @param string $idbase
 * @param boolean $return, return as string or just print it
 * @return string|void Based on value of $return
 */
function print_container_start($clearfix=false, $classes='', $idbase='', $return=false) {
    global $OUTPUT;
    if ($clearfix) {
        $classes .= ' clearfix';
    }
    $output = $OUTPUT->container_start($classes, $idbase);
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Simple function to end a container (see above)
 *
 * @deprecated
 * @param boolean $return, return as string or just print it
 * @return string|void Based on $return
 */
function print_container_end($return=false) {
    global $OUTPUT;
    $output = $OUTPUT->container_end();
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Print a bold message in an optional color.
 *
 * @deprecated use $OUTPUT->notification instead.
 * @param string $message The message to print out
 * @param string $style Optional style to display message text in
 * @param string $align Alignment option
 * @param bool $return whether to return an output string or echo now
 * @return string|bool Depending on $result
 */
function notify($message, $classes = 'notifyproblem', $align = 'center', $return = false) {
    global $OUTPUT;

    if ($classes == 'green') {
        debugging('Use of deprecated class name "green" in notify. Please change to "notifysuccess".', DEBUG_DEVELOPER);
        $classes = 'notifysuccess'; // Backward compatible with old color system
    }

    $output = $OUTPUT->notification($message, $classes);
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Print a continue button that goes to a particular URL.
 *
 * @deprecated since Moodle 2.0
 *
 * @param string $link The url to create a link to.
 * @param bool $return If set to true output is returned rather than echoed, default false
 * @return string|void HTML String if return=true nothing otherwise
 */
function print_continue($link, $return = false) {
    global $CFG, $OUTPUT;

    if ($link == '') {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $link = $_SERVER['HTTP_REFERER'];
            $link = str_replace('&', '&amp;', $link); // make it valid XHTML
        } else {
            $link = $CFG->wwwroot .'/';
        }
    }

    $output = $OUTPUT->continue_button($link);
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Print a standard header
 *
 * @param string  $title Appears at the top of the window
 * @param string  $heading Appears at the top of the page
 * @param string  $navigation Array of $navlinks arrays (keys: name, link, type) for use as breadcrumbs links
 * @param string  $focus Indicates form element to get cursor focus on load eg  inputform.password
 * @param string  $meta Meta tags to be added to the header
 * @param boolean $cache Should this page be cacheable?
 * @param string  $button HTML code for a button (usually for module editing)
 * @param string  $menu HTML code for a popup menu
 * @param boolean $usexml use XML for this page
 * @param string  $bodytags This text will be included verbatim in the <body> tag (useful for onload() etc)
 * @param bool    $return If true, return the visible elements of the header instead of echoing them.
 * @return string|void If return=true then string else void
 */
function print_header($title='', $heading='', $navigation='', $focus='',
                      $meta='', $cache=true, $button='&nbsp;', $menu=null,
                      $usexml=false, $bodytags='', $return=false) {
    global $PAGE, $OUTPUT;

    $PAGE->set_title($title);
    $PAGE->set_heading($heading);
    $PAGE->set_cacheable($cache);
    $PAGE->set_focuscontrol($focus);
    if ($button == '') {
        $button = '&nbsp;';
    }
    $PAGE->set_button($button);
    $PAGE->set_headingmenu($menu);

    // TODO $menu

    if ($meta) {
        throw new coding_exception('The $meta parameter to print_header is no longer supported. '.
                'You should be able to do everything you want with $PAGE->requires and other such mechanisms.');
    }
    if ($usexml) {
        throw new coding_exception('The $usexml parameter to print_header is no longer supported.');
    }
    if ($bodytags) {
        throw new coding_exception('The $bodytags parameter to print_header is no longer supported.');
    }

    $output = $OUTPUT->header();

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * This version of print_header is simpler because the course name does not have to be
 * provided explicitly in the strings. It can be used on the site page as in courses
 * Eventually all print_header could be replaced by print_header_simple
 *
 * @deprecated since Moodle 2.0
 * @param string $title Appears at the top of the window
 * @param string $heading Appears at the top of the page
 * @param string $navigation Premade navigation string (for use as breadcrumbs links)
 * @param string $focus Indicates form element to get cursor focus on load eg  inputform.password
 * @param string $meta Meta tags to be added to the header
 * @param boolean $cache Should this page be cacheable?
 * @param string $button HTML code for a button (usually for module editing)
 * @param string $menu HTML code for a popup menu
 * @param boolean $usexml use XML for this page
 * @param string $bodytags This text will be included verbatim in the <body> tag (useful for onload() etc)
 * @param bool   $return If true, return the visible elements of the header instead of echoing them.
 * @return string|void If $return=true the return string else nothing
 */
function print_header_simple($title='', $heading='', $navigation='', $focus='', $meta='',
                       $cache=true, $button='&nbsp;', $menu='', $usexml=false, $bodytags='', $return=false) {

    global $COURSE, $CFG, $PAGE, $OUTPUT;

    if ($meta) {
        throw new coding_exception('The $meta parameter to print_header is no longer supported. '.
                'You should be able to do everything you want with $PAGE->requires and other such mechanisms.');
    }
    if ($usexml) {
        throw new coding_exception('The $usexml parameter to print_header is no longer supported.');
    }
    if ($bodytags) {
        throw new coding_exception('The $bodytags parameter to print_header is no longer supported.');
    }

    $PAGE->set_title($title);
    $PAGE->set_heading($heading);
    $PAGE->set_focuscontrol($focus);
    $PAGE->set_cacheable(true);
    $PAGE->set_button($button);

    $output = $OUTPUT->header();

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

function print_footer($course = NULL, $usercourse = NULL, $return = false) {
    global $PAGE, $OUTPUT;
    debugging('print_footer() has been deprecated. Please change your code to use $OUTPUT->footer().');
    // TODO check arguments.
    if (is_string($course)) {
        debugging("Magic values like 'home', 'empty' passed to print_footer no longer have any effect. " .
                'To achieve a similar effect, call $PAGE->set_pagelayout before you call print_header.', DEBUG_DEVELOPER);
    } else if (!empty($course->id) && $course->id != $PAGE->course->id) {
        throw new coding_exception('The $course object you passed to print_footer does not match $PAGE->course.');
    }
    if (!is_null($usercourse)) {
        debugging('The second parameter ($usercourse) to print_footer is no longer supported. ' .
                '(I did not think it was being used anywhere.)', DEBUG_DEVELOPER);
    }
    $output = $OUTPUT->footer();
    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * This was used by old code to see whether a block region had anything in it,
 * and hence wether that region should be printed.
 *
 * We don't ever want old code to print blocks, so we now always return false.
 * The function only exists to avoid fatal errors in old code.
 *
 * @deprecated since Moodle 2.0. always returns false.
 *
 * @param object $blockmanager
 * @param string $region
 * @return bool
 */
function blocks_have_content(&$blockmanager, $region) {
    debugging('The function blocks_have_content should no longer be used. Blocks are now printed by the theme.');
    return false;
}

/**
 * This was used by old code to print the blocks in a region.
 *
 * We don't ever want old code to print blocks, so this is now a no-op.
 * The function only exists to avoid fatal errors in old code.
 *
 * @deprecated since Moodle 2.0. does nothing.
 *
 * @param object $page
 * @param object $blockmanager
 * @param string $region
 */
function blocks_print_group($page, $blockmanager, $region) {
    debugging('The function blocks_print_group should no longer be used. Blocks are now printed by the theme.');
}

/**
 * This used to be the old entry point for anyone that wants to use blocks.
 * Since we don't want people people dealing with blocks this way any more,
 * just return a suitable empty array.
 *
 * @deprecated since Moodle 2.0.
 *
 * @param object $page
 * @return array
 */
function blocks_setup(&$page, $pinned = BLOCKS_PINNED_FALSE) {
    debugging('The function blocks_print_group should no longer be used. Blocks are now printed by the theme.');
    return array(BLOCK_POS_LEFT => array(), BLOCK_POS_RIGHT => array());
}

/**
 * This iterates over an array of blocks and calculates the preferred width
 * Parameter passed by reference for speed; it's not modified.
 *
 * @deprecated since Moodle 2.0. Layout is now controlled by the theme.
 *
 * @param mixed $instances
 */
function blocks_preferred_width($instances) {
    debugging('The function blocks_print_group should no longer be used. Blocks are now printed by the theme.');
    $width = 210;
}

/**
 * Print a nicely formatted table.
 *
 * @deprecated since Moodle 2.0
 *
 * @param array $table is an object with several properties.
 */
function print_table($table, $return=false) {
    global $OUTPUT;
    // TODO MDL-19755 turn debugging on once we migrate the current core code to use the new API
    debugging('print_table() has been deprecated. Please change your code to use $OUTPUT->table().');
    $newtable = new html_table();
    foreach ($table as $property => $value) {
        if (property_exists($newtable, $property)) {
            $newtable->{$property} = $value;
        }
    }
    if (isset($table->class)) {
        $newtable->set_classes($table->class);
    }
    if (isset($table->rowclass) && is_array($table->rowclass)) {
        debugging('rowclass[] has been deprecated for html_table and should be replaced by rowclasses[]. please fix the code.');
        $newtable->rowclasses = $table->rowclass;
    }
    $output = $OUTPUT->table($newtable);
    if ($return) {
        return $output;
    } else {
        echo $output;
        return true;
    }
}

/**
 * Print a self contained form with a single submit button.
 *
 * @deprecated since Moodle 2.0
 *
 * @param string $link used as the action attribute on the form, so the URL that will be hit if the button is clicked.
 * @param array $options these become hidden form fields, so these options get passed to the script at $link.
 * @param string $label the caption that appears on the button.
 * @param string $method HTTP method used on the request of the button is clicked. 'get' or 'post'.
 * @param string $notusedanymore no longer used.
 * @param boolean $return if false, output the form directly, otherwise return the HTML as a string.
 * @param string $tooltip a tooltip to add to the button as a title attribute.
 * @param boolean $disabled if true, the button will be disabled.
 * @param string $jsconfirmmessage if not empty then display a confirm dialogue with this string as the question.
 * @param string $formid The id attribute to use for the form
 * @return string|void Depending on the $return paramter.
 */
function print_single_button($link, $options, $label='OK', $method='get', $notusedanymore='',
        $return=false, $tooltip='', $disabled = false, $jsconfirmmessage='', $formid = '') {
    global $OUTPUT;

    debugging('print_single_button() has been deprecated. Please change your code to use $OUTPUT->single_button().');

    // Cast $options to array
    $options = (array) $options;

    $button = new sibngle_button(new moodle_url($link, $options), $label, $method, array('disabled'=>$disabled, 'title'=>$tooltip, 'id'=>$id));

    if ($jsconfirmmessage) {
        $button->button->add_confirm_action($jsconfirmmessage);
    }

    $output = $OUTPUT->single_button($button);

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Print the specified user's avatar.
 *
 * @deprecated since Moodle 2.0
 *
 * @global object
 * @global object
 * @param mixed $user Should be a $user object with at least fields id, picture, imagealt, firstname, lastname
 *      If any of these are missing, or if a userid is passed, the the database is queried. Avoid this
 *      if at all possible, particularly for reports. It is very bad for performance.
 * @param int $courseid The course id. Used when constructing the link to the user's profile.
 * @param boolean $picture The picture to print. By default (or if NULL is passed) $user->picture is used.
 * @param int $size Size in pixels. Special values are (true/1 = 100px) and (false/0 = 35px) for backward compatibility
 * @param boolean $return If false print picture to current page, otherwise return the output as string
 * @param boolean $link enclose printed image in a link the user's profile (default true).
 * @param string $target link target attribute. Makes the profile open in a popup window.
 * @param boolean $alttext add non-blank alt-text to the image. (Default true, set to false for purely
 *      decorative images, or where the username will be printed anyway.)
 * @return string|void String or nothing, depending on $return.
 */
function print_user_picture($user, $courseid, $picture=NULL, $size=0, $return=false, $link=true, $target='', $alttext=true) {
    global $OUTPUT;

    debugging('print_user_picture() has been deprecated. Please change your code to use $OUTPUT->user_picture($user, array(\'courseid\'=>$courseid).');

    if (!is_object($user)) {
        $userid = $user;
        $user = new object();
        $user->id = $userid;
    }

    if (empty($user->picture) and $picture) {
        $user->picture = $picture;
    }

    $options = array('size'=>$size, 'link'=>$link, 'alttext'=>$alttext, 'courseid'=>$courseid, 'popup'=>!empty($target));

    $output = $OUTPUT->user_picture($user, $options);

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Prints a basic textarea field.
 *
 * @deprecated since Moodle 2.0
 *
 * When using this function, you should
 *
 * @global object
 * @param bool $usehtmleditor Enables the use of the htmleditor for this field.
 * @param int $rows Number of rows to display  (minimum of 10 when $height is non-null)
 * @param int $cols Number of columns to display (minimum of 65 when $width is non-null)
 * @param null $width (Deprecated) Width of the element; if a value is passed, the minimum value for $cols will be 65. Value is otherwise ignored.
 * @param null $height (Deprecated) Height of the element; if a value is passe, the minimum value for $rows will be 10. Value is otherwise ignored.
 * @param string $name Name to use for the textarea element.
 * @param string $value Initial content to display in the textarea.
 * @param int $obsolete deprecated
 * @param bool $return If false, will output string. If true, will return string value.
 * @param string $id CSS ID to add to the textarea element.
 * @return string|void depending on the value of $return
 */
function print_textarea($usehtmleditor, $rows, $cols, $width, $height, $name, $value='', $obsolete=0, $return=false, $id='') {
    /// $width and height are legacy fields and no longer used as pixels like they used to be.
    /// However, you can set them to zero to override the mincols and minrows values below.

    debugging('print_textarea() has been deprecated. Please change your code to use $OUTPUT->textarea().');

    global $CFG;

    $mincols = 65;
    $minrows = 10;
    $str = '';

    if ($id === '') {
        $id = 'edit-'.$name;
    }

    if ($usehtmleditor) {
        if ($height && ($rows < $minrows)) {
            $rows = $minrows;
        }
        if ($width && ($cols < $mincols)) {
            $cols = $mincols;
        }
    }

    if ($usehtmleditor) {
        editors_head_setup();
        $editor = get_preferred_texteditor(FORMAT_HTML);
        $editor->use_editor($id, array('legacy'=>true));
    } else {
        $editorclass = '';
    }

    $str .= "\n".'<textarea class="form-textarea" id="'. $id .'" name="'. $name .'" rows="'. $rows .'" cols="'. $cols .'">'."\n";
    if ($usehtmleditor) {
        $str .= htmlspecialchars($value); // needed for editing of cleaned text!
    } else {
        $str .= s($value);
    }
    $str .= '</textarea>'."\n";

    if ($return) {
        return $str;
    }
    echo $str;
}


/**
 * Print a help button.
 *
 * @deprecated since Moodle 2.0
 *
 * @param string $page  The keyword that defines a help page
 * @param string $title The title of links, rollover tips, alt tags etc
 *           'Help with' (or the language equivalent) will be prefixed and '...' will be stripped.
 * @param string $module Which module is the page defined in
 * @param mixed $image Use a help image for the link?  (true/false/"both")
 * @param boolean $linktext If true, display the title next to the help icon.
 * @param string $text If defined then this text is used in the page, and
 *           the $page variable is ignored. DEPRECATED!
 * @param boolean $return If true then the output is returned as a string, if false it is printed to the current page.
 * @param string $imagetext The full text for the helpbutton icon. If empty use default help.gif
 * @return string|void Depending on value of $return
 */
function helpbutton($page, $title, $module='moodle', $image=true, $linktext=false, $text='', $return=false, $imagetext='') {
    debugging('helpbutton() has been deprecated. Please change your code to use $OUTPUT->help_icon().');

    global $OUTPUT;

    $output = $OUTPUT->help_icon($page, $title, $module, $linktext);

    // hide image with CSS if needed

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Prints a single paging bar to provide access to other pages  (usually in a search)
 *
 * @deprecated since Moodle 2.0
 *
 * @param int $totalcount Thetotal number of entries available to be paged through
 * @param int $page The page you are currently viewing
 * @param int $perpage The number of entries that should be shown per page
 * @param mixed $baseurl If this  is a string then it is the url which will be appended with $pagevar, an equals sign and the page number.
 *                          If this is a moodle_url object then the pagevar param will be replaced by the page no, for each page.
 * @param string $pagevar This is the variable name that you use for the page number in your code (ie. 'tablepage', 'blogpage', etc)
 * @param bool $nocurr do not display the current page as a link (dropped, link is never displayed for the current page)
 * @param bool $return whether to return an output string or echo now
 * @return bool|string depending on $result
 */
function print_paging_bar($totalcount, $page, $perpage, $baseurl, $pagevar='page',$nocurr=false, $return=false) {
    global $OUTPUT;

    debugging('print_paging_bar() has been deprecated. Please change your code to use $OUTPUT->paging_bar($pagingbar).');

    if (empty($nocurr)) {
        debugging('the feature of parameter $nocurr has been removed from the moodle_paging_bar');
    }

    $pagingbar = moodle_paging_bar::make($totalcount, $page, $perpage, $baseurl);
    $pagingbar->pagevar = $pagevar;
    $output = $OUTPUT->paging_bar($pagingbar);

    if ($return) {
        return $output;
    }

    echo $output;
    return true;
}

/**
 * Given an array of values, output the HTML for a select element with those options.
 *
 * @deprecated since Moodle 2.0
 *
 * Normally, you only need to use the first few parameters.
 *
 * @param array $options The options to offer. An array of the form
 *      $options[{value}] = {text displayed for that option};
 * @param string $name the name of this form control, as in &lt;select name="..." ...
 * @param string $selected the option to select initially, default none.
 * @param string $nothing The label for the 'nothing is selected' option. Defaults to get_string('choose').
 *      Set this to '' if you don't want a 'nothing is selected' option.
 * @param string $script if not '', then this is added to the &lt;select> element as an onchange handler.
 * @param string $nothingvalue The value corresponding to the $nothing option. Defaults to 0.
 * @param boolean $return if false (the default) the the output is printed directly, If true, the
 *      generated HTML is returned as a string.
 * @param boolean $disabled if true, the select is generated in a disabled state. Default, false.
 * @param int $tabindex if give, sets the tabindex attribute on the &lt;select> element. Default none.
 * @param string $id value to use for the id attribute of the &lt;select> element. If none is given,
 *      then a suitable one is constructed.
 * @param mixed $listbox if false, display as a dropdown menu. If true, display as a list box.
 *      By default, the list box will have a number of rows equal to min(10, count($options)), but if
 *      $listbox is an integer, that number is used for size instead.
 * @param boolean $multiple if true, enable multiple selections, else only 1 item can be selected. Used
 *      when $listbox display is enabled
 * @param string $class value to use for the class attribute of the &lt;select> element. If none is given,
 *      then a suitable one is constructed.
 * @return string|void If $return=true returns string, else echo's and returns void
 */
function choose_from_menu ($options, $name, $selected='', $nothing='choose', $script='',
                           $nothingvalue='0', $return=false, $disabled=false, $tabindex=0,
                           $id='', $listbox=false, $multiple=false, $class='') {

    global $OUTPUT;
    debugging('choose_from_menu() has been deprecated. Please change your code to use $OUTPUT->select($select).');

    if ($script) {
        debugging('The $script parameter has been deprecated. You must use component_actions instead', DEBUG_DEVELOPER);
    }
    $select = html_select::make($options, $name, $selected);
    $select->nothinglabel = $nothing;
    $select->nothingvalue = $nothingvalue;
    $select->disabled = $disabled;
    $select->tabindex = $tabindex;
    $select->id = $id;
    $select->listbox = $listbox;
    $select->multiple = $multiple;
    $select->add_classes($class);

    if ($nothing == 'choose') {
        $select->nothinglabel = '';
    }

    $output = $OUTPUT->select($select);

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Just like choose_from_menu, but takes a nested array (2 levels) and makes a dropdown menu
 * including option headings with the first level.
 *
 * @deprecated since Moodle 2.0
 *
 * This function is very similar to {@link choose_from_menu_yesno()}
 * and {@link choose_from_menu()}
 *
 * @todo Add datatype handling to make sure $options is an array
 *
 * @param array $options An array of objects to choose from
 * @param string $name The XHTML field name
 * @param string $selected The value to select by default
 * @param string $nothing The label for the 'nothing is selected' option.
 *                        Defaults to get_string('choose').
 * @param string $script If not '', then this is added to the &lt;select> element
 *                       as an onchange handler.
 * @param string $nothingvalue The value for the first `nothing` option if $nothing is set
 * @param bool $return Whether this function should return a string or output
 *                     it (defaults to false)
 * @param bool $disabled Is the field disabled by default
 * @param int|string $tabindex Override the tabindex attribute [numeric]
 * @return string|void If $return=true returns string, else echo's and returns void
 */
function choose_from_menu_nested($options,$name,$selected='',$nothing='choose',$script = '',
                                 $nothingvalue=0,$return=false,$disabled=false,$tabindex=0) {

    debugging('choose_from_menu_nested() has been deprecated. Please change your code to use $OUTPUT->select($select).');
    global $OUTPUT;

    if ($script) {
        debugging('The $script parameter has been deprecated. You must use component_actions instead', DEBUG_DEVELOPER);
    }
    $select = html_select::make($options, $name, $selected);
    $select->tabindex = $tabindex;
    $select->disabled = $disabled;
    $select->nothingvalue = $nothingvalue;
    $select->nested = true;

    if ($nothing == 'choose') {
        $select->nothinglabel = '';
    }

    $output = $OUTPUT->select($select);

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Prints a simple button to close a window
 *
 * @deprecated since Moodle 2.0
 *
 * @global object
 * @param string $name Name of the window to close
 * @param boolean $return whether this function should return a string or output it.
 * @param boolean $reloadopener if true, clicking the button will also reload
 *      the page that opend this popup window.
 * @return string|void if $return is true, void otherwise
 */
function close_window_button($name='closewindow', $return=false, $reloadopener = false) {
    global $OUTPUT;

    debugging('close_window_button() has been deprecated. Please change your code to use $OUTPUT->close_window_button().');
    $output = $OUTPUT->close_window_button(get_string($name));

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Given an array of values, creates a group of radio buttons to be part of a form
 *
 * @deprecated since Moodle 2.0
 *
 * @staticvar int $idcounter
 * @param array  $options  An array of value-label pairs for the radio group (values as keys)
 * @param string $name     Name of the radiogroup (unique in the form)
 * @param string $checked  The value that is already checked
 * @param bool $return Whether this function should return a string or output
 *                     it (defaults to false)
 * @return string|void If $return=true returns string, else echo's and returns void
 */
function choose_from_radio ($options, $name, $checked='', $return=false) {

    debugging('choose_from_radio() has been deprecated. Please change your code to use $OUTPUT->select($select).');
    global $OUTPUT;

    $select = html_select::make($options, $name, $checked);
    $select->rendertype = 'radio';

    $output = $OUTPUT->select($select);

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * Display an standard html checkbox with an optional label
 *
 * @deprecated since Moodle 2.0
 *
 * @staticvar int $idcounter
 * @param string $name    The name of the checkbox
 * @param string $value   The valus that the checkbox will pass when checked
 * @param bool $checked The flag to tell the checkbox initial state
 * @param string $label   The label to be showed near the checkbox
 * @param string $alt     The info to be inserted in the alt tag
 * @param string $script If not '', then this is added to the checkbox element
 *                       as an onchange handler.
 * @param bool $return Whether this function should return a string or output
 *                     it (defaults to false)
 * @return string|void If $return=true returns string, else echo's and returns void
 */
function print_checkbox ($name, $value, $checked = true, $label = '', $alt = '', $script='',$return=false) {

    // debugging('print_checkbox() has been deprecated. Please change your code to use $OUTPUT->checkbox($checkbox).');
    global $OUTPUT;

    if (!empty($script)) {
        debugging('The use of the $script param in print_checkbox has not been migrated into $OUTPUT->checkbox. Please use $checkbox->add_action().', DEBUG_DEVELOPER);
    }

    $output = $OUTPUT->checkbox(html_select_option::make_checkbox($value, $checked, $label, $alt), $name);

    if (empty($return)) {
        echo $output;
    } else {
        return $output;
    }

}


/**
 * Display an standard html text field with an optional label
 *
 * @deprecated since Moodle 2.0
 *
 * @param string $name    The name of the text field
 * @param string $value   The value of the text field
 * @param string $alt     The info to be inserted in the alt tag
 * @param int $size Sets the size attribute of the field. Defaults to 50
 * @param int $maxlength Sets the maxlength attribute of the field. Not set by default
 * @param bool $return Whether this function should return a string or output
 *                     it (defaults to false)
 * @return string|void If $return=true returns string, else echo's and returns void
 */
function print_textfield ($name, $value, $alt = '',$size=50,$maxlength=0, $return=false) {

    debugging('print_textfield() has been deprecated. Please change your code to use $OUTPUT->textfield($field).');

    global $OUTPUT;

    $field = html_field::make_text($name, $value, $alt, $maxlength);
    $field->style = "width: {$size}px;";

    $output = $OUTPUT->textfield($field);

    if (empty($return)) {
        echo $output;
    } else {
        return $output;
    }

}

/**
 * Prints the 'update this xxx' button that appears on module pages.
 *
 * @deprecated since Moodle 2.0
 *
 * @param string $cmid the course_module id.
 * @param string $ignored not used any more. (Used to be courseid.)
 * @param string $string the module name - get_string('modulename', 'xxx')
 * @return string the HTML for the button, if this user has permission to edit it, else an empty string.
 */
function update_module_button($cmid, $ignored, $string) {
    global $CFG, $OUTPUT;

    // debugging('update_module_button() has been deprecated. Please change your code to use $OUTPUT->update_module_button().');

    //NOTE: DO NOT call new output method because it needs the module name we do not have here!

    if (has_capability('moodle/course:manageactivities', get_context_instance(CONTEXT_MODULE, $cmid))) {
        $string = get_string('updatethis', '', $string);

        $url = new moodle_url("$CFG->wwwroot/course/mod.php", array('update' => $cmid, 'return' => true, 'sesskey' => sesskey()));
        return $OUTPUT->single_button($url, $string);
    } else {
        return '';
    }
}

/**
 * Prints breadcrumb trail of links, called in theme/-/header.html
 *
 * This function has now been deprecated please use output's navbar method instead
 * as shown below
 *
 * <code php>
 * echo $OUTPUT->navbar();
 * </code>
 *
 * @deprecated since 2.0
 * @param mixed $navigation deprecated
 * @param string $separator OBSOLETE, and now deprecated
 * @param boolean $return False to echo the breadcrumb string (default), true to return it.
 * @return string|void String or null, depending on $return.
 */
function print_navigation ($navigation, $separator=0, $return=false) {
    global $OUTPUT,$PAGE;

    # debugging('print_navigation has been deprecated please update your theme to use $OUTPUT->navbar() instead', DEBUG_DEVELOPER);

    $output = $OUTPUT->navbar();

    if ($return) {
        return $output;
    } else {
        echo $output;
    }
}

/**
 * This function will build the navigation string to be used by print_header
 * and others.
 *
 * It automatically generates the site and course level (if appropriate) links.
 *
 * If you pass in a $cm object, the method will also generate the activity (e.g. 'Forums')
 * and activityinstances (e.g. 'General Developer Forum') navigation levels.
 *
 * If you want to add any further navigation links after the ones this function generates,
 * the pass an array of extra link arrays like this:
 * array(
 *     array('name' => $linktext1, 'link' => $url1, 'type' => $linktype1),
 *     array('name' => $linktext2, 'link' => $url2, 'type' => $linktype2)
 * )
 * The normal case is to just add one further link, for example 'Editing forum' after
 * 'General Developer Forum', with no link.
 * To do that, you need to pass
 * array(array('name' => $linktext, 'link' => '', 'type' => 'title'))
 * However, becuase this is a very common case, you can use a shortcut syntax, and just
 * pass the string 'Editing forum', instead of an array as $extranavlinks.
 *
 * At the moment, the link types only have limited significance. Type 'activity' is
 * recognised in order to implement the $CFG->hideactivitytypenavlink feature. Types
 * that are known to appear are 'home', 'course', 'activity', 'activityinstance' and 'title'.
 * This really needs to be documented better. In the mean time, try to be consistent, it will
 * enable people to customise the navigation more in future.
 *
 * When passing a $cm object, the fields used are $cm->modname, $cm->name and $cm->course.
 * If you get the $cm object using the function get_coursemodule_from_instance or
 * get_coursemodule_from_id (as recommended) then this will be done for you automatically.
 * If you don't have $cm->modname or $cm->name, this fuction will attempt to find them using
 * the $cm->module and $cm->instance fields, but this takes extra database queries, so a
 * warning is printed in developer debug mode.
 *
 * @deprecated since 2.0
 * @param mixed $extranavlinks - Normally an array of arrays, keys: name, link, type. If you
 *      only want one extra item with no link, you can pass a string instead. If you don't want
 *      any extra links, pass an empty string.
 * @param mixed $cm deprecated
 * @return array Navigation array
 */
function build_navigation($extranavlinks, $cm = null) {
    global $CFG, $COURSE, $DB, $SITE, $PAGE;

    if (is_array($extranavlinks) && count($extranavlinks)>0) {
        # debugging('build_navigation() has been deprecated, please replace with $PAGE->navbar methods', DEBUG_DEVELOPER);
        foreach ($extranavlinks as $nav) {
            if (array_key_exists('name', $nav)) {
                if (array_key_exists('link', $nav) && !empty($nav['link'])) {
                    $link = $nav['link'];
                } else {
                    $link = null;
                }
                $PAGE->navbar->add($nav['name'],$link);
            }
        }
    }

    return(array('newnav' => true, 'navlinks' => array()));
}

/**
 * Returns a small popup menu of course activity modules
 *
 * Given a course and a (current) coursemodule
 * his function returns a small popup menu with all the
 * course activity modules in it, as a navigation menu
 * The data is taken from the serialised array stored in
 * the course record
 *
 * @global object
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_COURSE
 * @param object $course A {@link $COURSE} object.
 * @param object $cm A {@link $COURSE} object.
 * @param string $targetwindow The target window attribute to us
 * @return string
 */
function navmenu($course, $cm=NULL, $targetwindow='self') {
    // This function has been deprecated with the creation of the global nav in
    // moodle 2.0

    return '';
}

?>
