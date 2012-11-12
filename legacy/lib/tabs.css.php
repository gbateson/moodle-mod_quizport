<?php
if (! headers_sent()) {
    header('Content-type: text/css');
}
$pixpath = 'http://';
switch (true) {
    case isset($_SERVER['SERVER_NAME']): $pixpath .= $_SERVER['SERVER_NAME']; break;
    case isset($_SERVER['HTTP_HOST']): $pixpath .= $_SERVER['HTTP_HOST']; break;
}
switch (true) {
    case isset($_SERVER['PHP_SELF']): $pixpath .= dirname(dirname($_SERVER['PHP_SELF'])); break;
    case isset($_SERVER['SCRIPT_NAME']): $pixpath .= dirname(dirname($_SERVER['SCRIPT_NAME'])); break;
    case isset($_SERVER['REQUEST_URI']): $pixpath .= dirname(dirname($_SERVER['REQUEST_URI'])); break;
    case isset($_SERVER['URL']): $pixpath .= dirname(dirname($_SERVER['URL'])); break;
}
$pixpath .= '/pix';
?>
/***
 *** tabs (styles_color.css)
 ***/

.tablink a:link,
.tablink a:visited {
  color:#000066;
}

.selected .tablink a:link,
.selected .tablink a:visited {
  color:#000000;
}
.tabs .side,
.tabrow td {
  border-color: #AAAAAA;
}
.tabrow td {
  background:url(<?php print $pixpath ?>/tab/left.gif) top left no-repeat;
}
.tabrow td .tablink {
  background:url(<?php print $pixpath ?>/tab/right.gif) top right no-repeat;
}
.tabrow td:hover {
  background-image:url(<?php print $pixpath ?>/tab/left_hover.gif);
}
.tabrow td:hover .tablink {
  background-image:url(<?php print $pixpath ?>/tab/right_hover.gif);
}
.tabrow .last {
  background: transparent url(<?php print $pixpath ?>/tab/right_end.gif) top right no-repeat;
}
.tabrow .selected {
  background:url(<?php print $pixpath ?>/tab/left_active.gif) top left no-repeat;
}
.tabrow .selected .tablink {
  background:url(<?php print $pixpath ?>/tab/right_active.gif) top right no-repeat;
}
.tabrow td.selected:hover {
  background-image:url(<?php print $pixpath ?>/tab/left_active_hover.gif);
}
.tabrow td.selected:hover .tablink {
  background-image:url(<?php print $pixpath ?>/tab/right_active_hover.gif);
}

/***
 *** Tabs (styles_fonts.css)
 ***/
.tablink {
  font-size:0.8em;
}

.tablink a:hover {
  text-decoration: none;
}

/***
 *** Tabs (styles_layout.css)
 ***/

.tabs {
  width: auto;
  margin-bottom: 15px;
  border-collapse: collapse;
}

.tabs td {
  padding: 0px;
}

.tabs .side {
  width: 50%;
  border-style: solid;
  border-width: 0px 0px 1px 0px;
}

.tabrow {
  border-collapse:collapse;
  width:100%;
  margin: 1px 0px 0px 0px;
}

.tabrow td {
  padding:0 0 0px 14px;
  border-style: solid;
  border-width: 0px 0px 1px 0px;
}

.tabrow th {
  display:none;
}
.tabrow td .tablink {
  display:block;
  padding:10px 14px 4px 0px;
  text-align:center;
  white-space:nowrap;
  text-decoration:none;
}
.tabrow .last {
  display:block;
  padding:0px 1px 0px 0px;
}

.tabrow td.selected {
  border-width: 0px;
}