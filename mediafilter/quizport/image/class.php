<?php
class quizport_mediaplayer_image extends quizport_mediaplayer {
    var $aliases = array('img');
    var $media_filetypes = array('gif','jpg','png');
    var $options = array(
        'width' => 0, 'height' => 0, 'build' => 0,
        'quality' => '', 'majorversion' => '', 'flashvars' => ''
    );
    var $spantext = '';
    var $removelink = false;

    function generate($filetype, $link, $mediaurl, $options) {
        $img = '<img src="'.$mediaurl.'"';
        if (array_key_exists('player', $options)) {
            unset($options['player']);
        }
        if (! array_key_exists('alt', $options)) {
            $options['alt'] = basename($mediaurl);
        }
        foreach ($options as $name => $value) {
            if ($value) {
                $img .= ' '.$name.'="'.$value.'"';
            }
        }
        $img .= ' />';
        return $img;
    }
}
?>