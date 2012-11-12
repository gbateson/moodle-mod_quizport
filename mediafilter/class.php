<?php
class quizport_mediafilter {

    // media filetypes that this filter can handle
    // this initial list is of the file types that Moodle's standard mediaplugin can handle
    // media file types specified by individual media players will be added to this list
    var $media_filetypes = array('avi','flv','mov','mpeg','mpg','mp3','ram','rpm','rm','swf','wmv');

    var $param_names = 'movie|src|url';
    //  wmp        : url
    //  quicktime  : src
    //  realplayer : src
    //  flash      : movie

    var $tagopen = '(?:(<)|(\\\\u003C))'; // left angle-bracket (uses two parenthese)
    var $tagchars = '(?(1)[^>]|(?(2).(?!\\\\u003E)))*?';  // string of chars inside the tag
    var $tagclose = '(?(1)>|(?(2)\\\\u003E))'; // right angle-bracket (to match the left one)
    var $tagreopen = '(?(1)<|(?(2)\\\\u003C))'; // another left angle-bracket (to match the first one)

    var $link_url = '';
    var $param_url = '';
    var $flashvars_url = '';
    var $querystring_url = '';

    var $js_inline = '';
    var $js_external = '';

    var $players  = array();
    var $defaultplayer = 'moodle';

    var $moodle_flashvars = array('waitForPlay', 'autoPlay', 'buffer');
    // bgColour, btnColour, btnBorderColour,
    // iconColour, iconOverColour,
    // trackColour, handleColour, loaderColour,
    // waitForPlay, autoPlay, buffer

    // constructor function
    function quizport_mediafilter(&$output) {
        global $CFG, $QUIZPORT, $THEME;

        $this->players[$this->defaultplayer] = new quizport_mediaplayer();

        $flashvars_mediaurls = array();
        $querystring_mediaurl = array();

        $players = get_list_of_plugins('mod/quizport/mediafilter/quizport'); // sorted
        foreach ($players as $player) {
            $filepath = $CFG->dirroot.'/mod/quizport/mediafilter/quizport/'.$player.'/class.php';
            if (file_exists($filepath) && include_once($filepath)) {
                $playerclass = 'quizport_mediaplayer_'.$player;
                $this->players[$player] = new $playerclass();

                // note the names urls in flashvars and querystring
                if ($name = $this->players[$player]->flashvars_mediaurl) {
                    $flashvars_mediaurls[$name] = true;
                }
                if ($name = $this->players[$player]->querystring_mediaurl) {
                    $querystring_mediaurls[$name] = true;
                }

                // add aliases to this player
                foreach ($this->players[$player]->aliases as $alias) {
                    $this->players[$alias] =&$this->players[$player];
                }

                // add any new media file types
                foreach ($this->players[$player]->media_filetypes as $filetype) {
                    if (! in_array($filetype, $this->media_filetypes)) {
                        $this->media_filetypes[] = $filetype;
                    }
                }
            }
        }

        $filetypes = implode('|', $this->media_filetypes);
        $filepath = '[^"'."']*".'\.(?:'.$filetypes.')[^"'."'\\\\]*";

        // detect backslash before double quotes and slashes within JavaScript
        $escape = '(?:\\\\)?';

        // pattern to match <a> tags which link to multimedia files
        //$this->link_url = '/'.$this->tagopen.'a'.'\s.*?'.'href="('.$filepath.')"'.'.*?'.$this->tagclose.'.*?'.$this->tagreopen.'\/a'.$this->tagclose.'/is';
        $this->link_url = '/'.$this->tagopen.'a'.'\s'.$this->tagchars.'href='.$escape.'"('.$filepath.')'.$escape.'"'.$this->tagchars.$this->tagclose.'.*?'.$this->tagreopen.$escape.'\/a'.$this->tagclose.'/is';

        // pattern to match <param> tags which contain the file path
        $this->param_url = '/'.$this->tagopen.'param'.'\s'.$this->tagchars.'name='.$escape.'"(?:'.$this->param_names.')'.$escape.'"'.$this->tagchars.'value='.$escape.'"('.$filepath.')'.$escape.'"'.$this->tagchars.$this->tagclose.'/is';

        if ($flashvars_mediaurls = implode('|', array_keys($flashvars_mediaurls))) {
            $this->flashvars_url = '/'.$this->tagopen.'param'.'\s+'.'name='.$escape.'"FlashVars'.$escape.'"'.'\s+'.'value='.$escape.'"(?:'.$flashvars_mediaurls.')=('.$filepath.')'.$escape.'"'.$this->tagchars.$this->tagclose.'/is';
        }

        if ($querystring_mediaurls = implode('|', array_keys($querystring_mediaurls))) {
            // could you this to detect url in query string
            $filepath_querystring = $filepath.'\?[^"'."']*((?:$querystring_mediaurls)".'=[^"'."=']*)".'[^"'."']*";
            $this->querystring_url = '/'.$this->tagopen.'a'.'\s'.$this->tagchars.'href='.$escape.'"'.$filepath_querystring.$escape.'"'.$this->tagchars.$this->tagclose.'.*?'.$this->tagreopen.$escape.'\/a'.$this->tagclose.'/is';
        }

        // check player settings
        $names = array_keys($this->players);
        foreach ($names as $name) {

            // convert  player url to absolute url
            $player = &$this->players[$name];
            if ($player->playerurl && ! preg_match('/^(?:https?:)?\/+/i', $player->playerurl)) {
                $player->playerurl = $CFG->wwwroot.'/mod/quizport/mediafilter/quizport/'.$player->playerurl;
            }

            // set basic flashvars settings
            $options = &$player->options;
            if (is_null($options['flashvars'])) {
                if (empty($THEME->filter_mediaplugin_colors)) {
                    $options['flashvars'] = ''
                        .'bgColour=000000&'
                        .'btnColour=ffffff&'.'btnBorderColour=cccccc&'
                        .'iconColour=000000&'.'iconOverColour=00cc00&'
                        .'trackColour=cccccc&'.'handleColour=ffffff&'
                        .'loaderColour=ffffff&'.'waitForPlay=yes'
                    ;
                } else {
                    // You can set this up in your theme/xxx/config.php
                    $options['flashvars'] = $THEME->filter_mediaplugin_colors;
                }
                $options['flashvars'] = htmlspecialchars($options['flashvars']);
            }
        }
    }

    // methods
    function fix(&$output, $text) {
        $this->fix_objects($output, $text);
        $this->fix_links($output, $text);
        $this->fix_specials($output, $text);
    }

    function fix_objects(&$output, $text) {
        $search = '/'.$this->tagopen.'object'.'\s'.$this->tagchars.$this->tagclose.'(.*?)(?:'.$this->tagreopen.'(?:\\\\)?'.'\/object'.$this->tagclose.')+/ise';
        // Segments[0][0] = '<object classid=\"CLSID:6BF52A52-394A-11d3-B153-00C04F79FAA6\" width=\"100\" height=\"30\"><param name=\"url\" value=\"http://localhost/moodle/19/mysql/file.php/2/hennyjellema/frag.01.mp3\" /><param name=\"autostart\" value=\"false\" /><param name=\"showcontrols\" value=\"true\" /><\/object>';
        // $search = '/<object[^>]*>(.*?)(?:'.'(?:\\\\)*'.'\/object>)+'.'/ise';
        $replace = '$this->fix_object($output,"\\0","\\2")';
        $output->$text = preg_replace($search, $replace, $output->$text);
    }

    function fix_object(&$output, $object, $unicode, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $object = str_replace('\\'.$quote, $quote, $object);
        }

        if ($this->flashvars_url && preg_match($this->flashvars_url, $object, $matches)) {
            $url = $matches[3]; // from flashvars
        } else if ($this->querystring_url && preg_match($this->querystring_url, $object, $matches)) {
            $url = $matches[3]; // from querystring
        } else if (preg_match($this->param_url, $object, $matches)) {
            $url = $matches[3]; // from param
        } else if (preg_match($this->link_url, $object, $matches)) {
            $url = $matches[3]; // from link
        } else {
            // no url found - shouldn't happen !!
            return $object;
        }

        // strip inner tags (e.g. <embed>)
        $txt = preg_replace('/'.$this->tagopen.'.*?'.$this->tagclose.'/', '', $object);
        $txt = trim($txt);

        // if url has a query string, we assume the target url
        // is one of the values in the query string
        // $pos : 0=first value, 1=named value, 2=last value
        $pos = 1;
        switch ($pos) {
            case 0: $search = '/^[^?]*\?'.'[^=]+=([^&]*)'.'.*$/'; break;
            case 1: $search = '/^[^?]*\?'.'(?:file|src|thesound|mp3)+=([^&]*)'.'.*$/'; break;
            case 2: $search = '/^[^?]*\?'.'(?:[^=]+=[^&]*&(?:amp;))*'.'[^=]+=([^&]*)'.'$/'; break;
        }
        $url = preg_replace($search, '$1', $url, 1);

        // create new media player for this media file
        $link = '<a href="'.$url.'">'.$txt.'</a>';
        return $this->fix_link($output, $link, $unicode, '');
    }

    function fix_specials(&$output, $text) {
        // search for [url   player   width   height   options]
        //     url : the (relative or absolute) url of the media file
        //     player : string of alpha chars (underscore and hyphen are also allowed)
        //         "img" or "image" : insert an <img> tag for this url
        //         "a" or "link" : insert a link to the url
        //         "object" or "movie" : url is a stand-alone movie; insert <object> tags
        //         "moodle" : insert a standard moodle media player to play the media file
        //         otherwise the url is for a media file, so insert a player to play/display it
        //     width : the required display width (e.g. 50 or 50px or 10em)
        //     height : the required display height (e.g. 25 or 25px or 5em)
        //     options : xx OR xx= OR xx=abc123 OR xx="a b c 1 2 3"
        // Note: only url is required; others values are optional
        $filetypes = implode('|', $this->media_filetypes);
        $search = ''
            .'/\[\s*'
            .'('.'[^ \]]*?'.'\.(?:'.$filetypes.')(?:\?[^ \]]*)?)' // 1: url (+ querystring)
            .'(\s+[a-z][0-9a-z._-]*)?' // 2: player
            .'(\s+\d+(?:\.\d+)?[a-z]*)?' // 3: width
            .'(\s+\d+(?:\.\d+)?[a-z]*)?' // 4: height
            .'((?:\s+[^ =\]]+(?:=(?:(?:\\\\?"[^"]*")|\w*))?)*)' // 5: options
            .'\s*\]'
            .'((?:\s*<br\s*\/?>)*)' // 6: trailing newlines
            .'/ise'
        ;
        $replace = '$this->fix_special($output,"\\1",trim("\\2"),trim("\\3"),trim("\\4"),trim("\\5"),trim("\\6"))';
        $output->$text = preg_replace($search, $replace, $output->$text);
    }

    function fix_special(&$output, $url, $player, $width, $height, $options, $space, $quote="'") {
        if ($quote) {
            // fix quotes escaped by preg_replace
            $url = str_replace('\\'.$quote, $quote, $url);
            $player = str_replace('\\'.$quote, $quote, $player);
            $width = str_replace('\\'.$quote, $quote, $width);
            $height = str_replace('\\'.$quote, $quote, $height);
            $options = str_replace('\\'.$quote, $quote, $options);
            $space = str_replace('\\'.$quote, $quote, $space);
        }

        // convert $url to $absoluteurl
        $absoluteurl = $output->convert_url_relative($output->source->baseurl, $output->source->filepath, '', $url, '', '');
        //$absoluteurl = $output->convert_url($output->source->baseurl, $output->sourcefile, $url, '');

        // set height equal to width, if necessary
        if ($width && ! $height) {
            $height = $width;
        }

        // $options_array will be passed to mediaplugin_filter
        $options_array = array();

        // add $player, $width and $height to $option_array
        if ($player=='movie' || $player=='object') {
            $options_array['movie'] = $absoluteurl;
            $options_array['skipmediaurl'] = true;
        } else if ($player=='center' || $player=='hide' || $player=='iconlink') {
            $options_array[$player] = true;
            $player = '';
        } else if ($player) {
            $options_array['player'] = $player;
        }

        if ($width) {
            $options_array['width'] = $width;
        }
        if ($height) {
            $options_array['height'] = $height;
        }

        // transfer $options to $option_array
        if (preg_match_all('/([^ =\]]+)(=((?:\\\\?"[^"]*")|\w*))?/s', $options, $matches)) {
            $i_max = count($matches[0]);
            for ($i=0; $i<$i_max; $i++) {
                $name = $matches[1][$i];
                if ($matches[2][$i]) {
                    $options_array[$name] = trim($matches[3][$i], '"\\');
                } else {
                    $options_array[$name] = true; // boolean switch
                }
            }
        }

        // remove trailing space if player is to be centered or hidden
        if (! empty($options_array['center']) || ! empty($options_array['hide'])) {
            $space = '';
        }

        $link = '<a href="'.$absoluteurl.'" target="_blank">'.$url.'</a>';
        return $this->fix_link($output, $link, '', '', $options_array).$space;
    }

    function fix_links(&$output, $text) {
        $search = $this->link_url.'e';
        $replace = '$this->fix_link($output,"\\0","\\2")';
        $output->$text = preg_replace($search, $replace, $output->$text);
    }

    function fix_link(&$output, $link, $unicode, $quote="'", $options=array()) {
        global $CFG, $QUIZPORT;
        static $eolas_fix_applied = 0;

        if ($quote) {
            // fix quotes escaped by preg_replace
            $link = str_replace('\\'.$quote, $quote, $link);
        }

        // set player default, if necessary
        if (empty($options['player'])) {
            $options['player'] = $this->defaultplayer;
        }

        // hide player if required
        if (array_key_exists('hide', $options)) {
            if ($options['hide']) {
                $options['width'] = 1;
                $options['height'] = 1;
                if ($options['player']=='moodle') {
                    $options['autoPlay'] = 'yes';
                    $options['waitForPlay'] = 'no';
                }
            }
            unset($options['hide']);
            unset($options['center']);
        }

        // call filter to add media player
        if (empty($options['movie']) && $options['player']=='moodle') {
            $object = mediaplugin_filter($QUIZPORT->courserecord->id, $link);
            if ($object==$link) {
                // do nothing
            } else if ($eolas_fix_applied==$QUIZPORT->quiz->id) {
                // eolas_fix.js and ufo.js have already been added for this quiz
            } else {
                if ($eolas_fix_applied==0) {
                    // 1st quiz - eolas_fix.js was added by filter/mediaplugin/filter.php
                } else {
                    // 2nd (or later) quiz - e.g. we are being called by quizport_cron()
                    $object .= '<script defer="defer" src="'.$CFG->wwwroot.'/filter/mediaplugin/eolas_fix.js" type="text/javascript"></script>';
                }
                $object .= '<script type="text/javascript" src="'.$CFG->wwwroot.'/lib/ufo.js"></script>';
                $eolas_fix_applied = $QUIZPORT->quiz->id;
            }
            $replace = '$this->fix_flashvars("\\1", "\\2", "\\3", $options)';
            if ($CFG->majorrelease>=1.8) {
                // flashvars:"..."
                $search = '/(flashvars:")([^"]*)(")/e';
            } else {
                // <object ... > ... <param name="flashvars" value="..." />
                // ... <embed ... flashvars="..." ... > ... </object>
                $search = array(
                    '/(name="flashvars" value=")([^"]*)(")/e',
                    '/(flashvars=")([^"]*)(")/e'
                );
            }
            $object = preg_replace($search, $replace, $object);
            foreach (array('width', 'height') as $option) {
                if (array_key_exists($option, $options)) {
                    if ($CFG->majorrelease>=1.8) {
                        // width:"90", height:"15"
                        $search = '/(?<='.$option.':")\w+(?=")/i';
                    } else {
                        // width="90" height="15"
                        $search = '/(?<='.$option.'=")\w+(?=")/i';
                    }
                    $object = preg_replace($search, $options[$option], $object);
                }
            }
        } else {
            $object = $this->mediaplugin_filter($QUIZPORT->courserecord->id, $link, $options);
        }

        // center content if required
        if (array_key_exists('iconlink', $options)) {
            if ($options['iconlink'] && preg_match('/href="([^"]*)"/', $link, $matches)) {
                $onclick = "this.target='iconlink'; ".
                           "var w = Math.min(600, screen.width); ".
                           "var h = Math.min(60, screen.height); ".
                           "var l = (screen.width - w) / 2; ".
                           "var t = (screen.height - h) / 2; ".
                           "var newwin=window.open('".$matches[1]."', 'iconlink', 'menubar=0,location=0,scrollbars,resizable,width='+w+',height='+h+',top='+t+',left='+l);".
                           "if (newwin) newwin.focus(); ".
                           "newwin = null; ".
                           "return false;";
                $img = '<img src="'.$CFG->wwwroot.'/pix/f/audio.gif" title="audio" '.
                       'style="border-style: none; border-left: 4px solid transparent;" />';
                $object .= '<a onclick="'.str_replace("'", '&apos;', $onclick).'">'.$img.'</a>';
            }
            unset($options['iconlink'], $matches, $onclick, $img);
            }

        // center content if required
        if (array_key_exists('center', $options)) {
            if ($options['center']) {
                $object = '<div style="text-align:center;">'.$object.'</div>';
            }
            unset($options['center']);
        }

        if (strcmp($object, $link)) {

            $player = $options['player'];
            if ($this->players[$player]->removelink) {
                // remove the link to MP3 audio etc
                // $object = str_replace($link, '', $object);
                $object = preg_replace('/<a href="[^"]*"[^>]*>[^<]*<\/a>\s*/i', '', $object);
            }

            // extract the external javascripts
            $search = '/\s*<script[^>]*src[^>]*>.*?<\/script>\s*/is';
            if (preg_match_all($search, $object, $scripts, PREG_OFFSET_CAPTURE)) {
                foreach (array_reverse($scripts[0]) as $script) {
                    // $script: [0] = matched string, [1] = offset to start of string
                    // remove the javascript from the player
                    $object = substr_replace($object, "\n", $script[1], strlen($script[0]));
                    // store this javascript so it can be run later
                    $this->js_external = trim($script[0])."\n".$this->js_external;
                }
            }

            // extract the inline javascripts
            $search = '/\s*<script[^>]*>.*?<\/script>\s*/is';
            if (preg_match_all($search, $object, $scripts, PREG_OFFSET_CAPTURE)) {
                foreach (array_reverse($scripts[0]) as $script) {
                    // $script: [0] = matched string, [1] = offset to start of string
                    // remove the script from the player
                    $object = substr_replace($object, "\n", $script[1], strlen($script[0]));
                    // format the script (helps readability of the html source)
                    $script[0] = $this->format_script($script[0]);
                    // store this javascript so it can be run later
                    $this->js_inline = trim($script[0])."\n".$this->js_inline;
                }
            }
        }

        // remove white space between tags, standardize other white space to a single space
        $object = preg_replace('/(?<=>)\s+(?=<)/', '', $object);
        $object = preg_replace('/\s+/', ' ', $object);

        if ($unicode) {
            // encode angle brackets as javascript $unicode
            $object = str_replace('<', '\\u003C', $object);
            $object = str_replace('>', '\\u003E', $object);
            //$object = str_replace('&amp;', '&', $object);
        }

        return $object;
    }

    function fix_flashvars($before, $flashvars, $after, &$options, $quote="'") {
        global $CFG;
        if ($quote) {
            $before = str_replace('\\'.$quote, $quote, $before);
            $flashvars = str_replace('\\'.$quote, $quote, $flashvars);
            $after = str_replace('\\'.$quote, $quote, $after);
        }
        if ($CFG->majorrelease>=1.8) {
            // html_entity_decode() is required undo the call to htmlentities(), see MDL-5223
            // this is necessary to allow waitForPlay and autoPlay to be effective on Firefox
            $flashvars = html_entity_decode($flashvars);
        }
        $vars = explode('&', $flashvars);
        foreach ($this->moodle_flashvars as $var) {
            if (array_key_exists($var, $options)) {
                $vars = preg_grep("/^$var=/", $vars, PREG_GREP_INVERT);
                $vars[] = "$var=".$options[$var];
            }
        }
        //$vars = array_map('htmlentities', $vars);
        return $before.implode('&', $vars).$after;
    }

    function format_script($script, $quote="'") {
        if ($quote) {
            $script = str_replace('\\'.$quote, $quote, $script);
        }
        // format FO (Flash Object) properties (one property per line)
        $search = '/(var FO)\s*=\s*\{\s*(.*?)\s*\}/ise';
        $replace = '"\\1 = {".$this->format_script_FO("\\2")."\\n  }"';
        return preg_replace($search, $replace, $script);
    }

    function format_script_FO($properties, $quote="'") {
        if ($quote) {
            $properties = str_replace('\\'.$quote, $quote, $properties);
        }
        // $1 : the name of an FO object property
        // $2 : the value of an FO object property
        $search = '/\s*(\w+)\s*:\s*(".*?")/ise';
        $replace = 'sprintf("% -5s% -6s : %s", "\\n", "\\1", "\\2")';
        return preg_replace($search, $replace, $properties);
    }

    function mediaplugin_filter($courseid, $text, $options=array()) {
        // this function should be overloaded by the subclass
        return $text;
    }
}

class quizport_mediaplayer {
    var $aliases = array();
    var $playerurl = '';
    var $flashvars = array();
    var $flashvars_mediaurl = '';
    var $querystring_mediaurl = '';
    var $options = array(
        'width' => 0, 'height' => 0, 'build' => 40,
        'quality' => 'high', 'majorversion' => '6', 'flashvars' => null
    );
    var $more_options = array();
    var $media_filetypes = array();
    var $spantext = '';
    var $removelink = true;

    // constructor function
    function quizport_mediaplayer() {
        $this->options = array_merge($this->options, $this->more_options);
    }

    // generate output
    function generate($filetype, $link, $mediaurl, $options) {
        global $CFG;

        // cache language strings
        static $str;
        if (! isset($str->$filetype)) {
            $str->$filetype = get_string($filetype.'audio', 'mediaplugin');
        }

        // $id must be unique to prevent it being stored in Moodle's text cache
        static $id_count = 0;
        $id = str_replace('quizport_mediaplayer_', '', get_class($this)).'_'.time().sprintf('%02d', ($id_count++));


        // add movie id to $options, if necessary
        // this is required in order to allow Flash addCallback on IE
        // 2009/11/30 - it is not necessary for IE8, maybe not necessary at all
        //if (! isset($options['id'])) {
        //    $options['id'] = 'ufo_'.$id;
        //}

        // add movie url to $options, if necessary
        if (! isset($options['movie'])) {
            $options['movie'] = $this->playerurl;
            if ($this->querystring_mediaurl) {
                $options['movie'] .= '?'.$this->querystring_mediaurl.'='.$mediaurl;
            }
        }

        // do we need to make sure the mediaurl is added to flashvars?
        if ($this->flashvars_mediaurl && empty($options['skipmediaurl'])) {
            $find_mediaurl = true;
        } else {
            $find_mediaurl = false;
        }

        // get list of option names to be cleaned
        $search = '/^player|playerurl|querystring_mediaurl|flashvars_mediaurl|skipmediaurl$/i';
        $names = preg_grep($search, array_keys($options), PREG_GREP_INVERT);

        // clean the options
        foreach ($names as $name) {

            switch ($name) {

                case 'id':
                    // allow a-z A-Z 0-9 and underscore (could use PARAM_SAFEDIR, but that allows hyphen too)
                    $options[$name] = preg_replace('/\W/', '', $options[$name]);
                    break;

                case 'movie':
                    // clean_param() will reject the url if it contains spaces
                    $options[$name] = str_replace(' ', '%20', $options[$name]);
                    $options[$name] = clean_param($options[$name], PARAM_URL);
                    break;

                case 'flashvars':

                    // split flashvars into an array
                    $flashvars = str_replace('&amp;', '&', $options[$name]);
                    $flashvars = explode('&', $flashvars);

                    // loop through $flashvars, cleaning as we go
                    $options[$name] = array();
                    $found_mediaurl = false;
                    foreach ($flashvars as $flashvar) {
                        if (trim($flashvar)=='') {
                            continue;
                        }
                        list($n, $v) = explode('=', $flashvar, 2);
                        $n = clean_param($n, PARAM_ALPHANUM);
                        if ($n==$this->flashvars_mediaurl) {
                            $found_mediaurl = true;
                            $options[$name][$n] = clean_param($v, PARAM_URL);
                        } else if (array_key_exists($n, $this->flashvars)) {
                            $options[$name][$n] = clean_param($v, $this->flashvars[$n]);
                        } else {
                            // $flashvar not defined for this media player so ignore it
                        }
                    }

                    // add media url to flashvars, if necessary
                    if ($find_mediaurl && ! $found_mediaurl) {
                        $n = $this->flashvars_mediaurl;
                        $options[$name][$n] = clean_param($mediaurl, PARAM_URL);
                    }

                    // add flashvars values passed via $options
                    foreach ($this->flashvars as $n => $type) {
                        if (isset($options[$n])) {
                            $options[$name][$n] = clean_param($options[$n], $type);
                            unset($options[$n]);
                        }
                    }

                    // rebuild $flashvars
                    $flashvars = array();
                    foreach ($options[$name] as $n => $v) {
                        $flashvars[] = "$n=".urlencode($v);
                    }

                    // join $namevalues back together
                    $options[$name] = implode('&', $flashvars);
                    unset($flashvars);
                    break;

                default:
                    $quote = '';
                    $value = $options[$name];
                    if (preg_match('/^(\\\\*["'."']".')?(.*)'.'\\1'.'$/', $value, $matches)) {
                        $quote = $matches[1];
                        $value = $matches[2];
                    }
                    $options[$name] = $quote.clean_param($value, PARAM_ALPHANUM).$quote;
            } // end switch $name
        } // end foreach $names

        // re-order options ("movie" first, "flashvars" last)
        $names = array_merge(
            array('id'), array('movie'),
            preg_grep('/^id|movie|flashvars$/i', $names, PREG_GREP_INVERT),
            array('flashvars')
        );

        // format player properties (JSON format: http://www.json.org)
        $properties = array();
        foreach ($names as $name) {
            if (isset($options[$name]) && $options[$name]) {
                $properties[] = $name.':"'.$this->obfuscate_js(addslashes_js($options[$name])).'"';
            }
        }
        $properties = implode(',', $properties);

        if (strlen($this->spantext)) {
            $spantext = $this->spantext;
        } else {
            $size = '';
            if (isset($options['width'])) {
                $size .= ' width="'.$options['width'].'"';
            }
            if (isset($options['height'])) {
                $size .= ' height="'.$options['height'].'"';
            }
            $spantext = '<img src="'.$CFG->wwwroot.'/pix/spacer.gif"'.$size.' alt="'.$str->$filetype.'" />';
        }

        return $link
            .'<span class="mediaplugin_'.$filetype.'" id="'.$id.'">'.$spantext.'</span>'."\n"
            .'<script type="text/javascript">'."\n"
            .'//<![CDATA['."\n"
            .'  var FO = { '.$properties.' };'."\n"
            .'  UFO.create(FO, "'.$this->obfuscate_js($id).'");'."\n"
            .'//]]>'."\n"
            .'</script>'
        ;
    }

    function obfuscate_js($str) {
        global $CFG;

        if (empty($CFG->quizport_enableobfuscate)) {
            return $str;
        }

        $obfuscated = '';
        $strlen = strlen($str);
        for ($i=0; $i<$strlen; $i++) {
            if ($i==0 || mt_rand(0,2)) {
                $obfuscated .= '\\u'.sprintf('%04X', ord($str{$i}));
            } else {
                $obfuscated .= $str{$i};
            }
        }
        return $obfuscated;
    }
}
?>