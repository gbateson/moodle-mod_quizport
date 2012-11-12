<?php
class quizport_mediaplayer_link extends quizport_mediaplayer {
    var $aliases = array('a');
    var $options = array(
        'width' => 0, 'height' => 0, 'build' => 0,
        'quality' => '', 'majorversion' => '', 'flashvars' => ''
    );
    var $spantext = '';
    var $removelink = false;
    var $media_filetypes = array('...'); // 'htm','html','pdf'

    function generate($filetype, $link, $mediaurl, $options) {
        $a = '<a href="'.$mediaurl.'"';
        if (array_key_exists('player', $options)) {
            unset($options['player']);
        }
        if (array_key_exists('onclick', $options)) {
            $a .= ' onclick="'.$options['onclick'].'"';
            unset($options['onclick']);
        } else {
            $a .= ' target="_blank"';
        }
        if (array_key_exists('text', $options)) {
            $text = $options['text'];
            unset($options['text']);
        } else {
            $text = $mediaurl;
        }
        foreach ($options as $name => $value) {
            if ($value) {
                $a .= ' '.$name.'="'.$value.'"';
            }
        }
        return $a.'>'.$text.'</a>';
    }
}
?>