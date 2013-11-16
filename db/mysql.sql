##
## Table structure for table 'quizport'
##
CREATE TABLE prefix_quizport (
  id bigint(10) unsigned NOT NULL auto_increment,
  course bigint(10) unsigned NOT NULL default '0',
  `name` varchar(255) NOT NULL default '',
  timecreated bigint(10) unsigned NOT NULL default '0',
  timemodified bigint(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) COMMENT='details of QuizPort activities';
ALTER TABLE prefix_quizport ADD INDEX prefix_quizport_cou_ix (course);

##
## Table structure for table 'quizport_units'
##
CREATE TABLE prefix_quizport_units (
  id bigint(10) unsigned NOT NULL auto_increment,
  parenttype tinyint(2) unsigned NOT NULL,
  parentid bigint(10) unsigned NOT NULL default '0',
  entrycm bigint(10) NOT NULL default '0',
  entrygrade mediumint(6) unsigned NOT NULL default '100',
  entrypage tinyint(2) unsigned NOT NULL default '0',
  entrytext text NOT NULL,
  entryoptions bigint(10) unsigned NOT NULL default '0',
  exitpage tinyint(2) unsigned NOT NULL default '0',
  exittext text NOT NULL,
  exitoptions bigint(10) unsigned NOT NULL default '0',
  exitcm bigint(10) NOT NULL default '0',
  exitgrade mediumint(6) unsigned NOT NULL default '0',
  showpopup tinyint(2) unsigned NOT NULL default '0',
  popupoptions varchar(255) NOT NULL default '',
  timeopen bigint(10) unsigned NOT NULL default '0',
  timeclose bigint(10) unsigned NOT NULL default '0',
  timelimit bigint(10) unsigned NOT NULL default '0',
  delay1 bigint(10) unsigned NOT NULL default '0',
  delay2 bigint(10) unsigned NOT NULL default '0',
  `password` varchar(255) NOT NULL default '',
  subnet varchar(255) NOT NULL default '',
  allowresume tinyint(2) unsigned NOT NULL default '1',
  allowfreeaccess mediumint(6) NOT NULL default '0',
  attemptlimit mediumint(6) unsigned NOT NULL default '0',
  attemptgrademethod smallint(4) unsigned NOT NULL default '0',
  grademethod smallint(4) unsigned NOT NULL default '1',
  gradeignore tinyint(2) unsigned NOT NULL default '0',
  gradelimit mediumint(6) unsigned NOT NULL default '100',
  gradeweighting mediumint(6) default NULL default '100',
  PRIMARY KEY  (id)
) COMMENT='details of QuizPort units';
ALTER TABLE prefix_quizport_units ADD UNIQUE INDEX prefix_quizunit_parpar_uix (parenttype,parentid);

##
## Table structure for table 'quizport_unit_grades'
##
CREATE TABLE prefix_quizport_unit_grades (
  id bigint(10) unsigned NOT NULL auto_increment,
  parenttype tinyint(2) unsigned default '0',
  parentid bigint(10) unsigned NOT NULL default '0',
  userid bigint(10) unsigned NOT NULL,
  grade mediumint(6) NOT NULL default '0',
  `status` smallint(4) unsigned default '0',
  duration bigint(10) unsigned NOT NULL,
  timemodified bigint(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) COMMENT='details of QuizPort activity grades';
ALTER TABLE prefix_quizport_unit_grades ADD INDEX prefix_quizunitgrad_parpar_ix (parenttype,parentid);
ALTER TABLE prefix_quizport_unit_grades ADD INDEX prefix_quizunitgrad_use_ix (userid);

##
## Table structure for table 'quizport_unit_attempts'
##
CREATE TABLE prefix_quizport_unit_attempts (
  id bigint(10) NOT NULL auto_increment,
  unitid bigint(10) unsigned NOT NULL,
  unumber mediumint(6) unsigned NOT NULL,
  userid bigint(10) unsigned NOT NULL,
  grade mediumint(6) unsigned NOT NULL,
  `status` smallint(4) unsigned NOT NULL,
  duration bigint(10) unsigned NOT NULL,
  timemodified bigint(10) unsigned NOT NULL,
  PRIMARY KEY  (id)
) COMMENT='details of the grades for attempts at QuizPort units';
ALTER TABLE prefix_quizport_unit_attempts ADD INDEX prefix_quizunitatte_uni_ix (unitid);
ALTER TABLE prefix_quizport_unit_attempts ADD INDEX prefix_quizunitatte_use_ix (userid);
ALTER TABLE prefix_quizport_unit_attempts ADD UNIQUE INDEX prefix_quizunitatte_useuniunu_uix (userid,unitid,unumber);

##
## Table structure for table 'quizport_quizzes'
##
CREATE TABLE prefix_quizport_quizzes (
  id bigint(10) unsigned NOT NULL auto_increment,
  unitid bigint(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL default '',
  sourcefile varchar(255) NOT NULL default '',
  sourcetype varchar(255) NOT NULL default '',
  sourcelocation tinyint(2) unsigned NOT NULL default '0',
  configfile varchar(255) NOT NULL default '',
  configlocation tinyint(2) unsigned NOT NULL default '0',
  outputformat varchar(255) NOT NULL default '',
  navigation mediumint(6) unsigned NOT NULL default '0',
  title mediumint(6) unsigned NOT NULL default '3',
  stopbutton tinyint(2) unsigned NOT NULL default '0',
  stoptext varchar(255) NOT NULL default '',
  allowpaste tinyint(2) unsigned NOT NULL default '0',
  usefilters tinyint(2) unsigned NOT NULL default '0',
  useglossary tinyint(2) unsigned NOT NULL default '0',
  usemediafilter varchar(255) NOT NULL default '',
  studentfeedback smallint(4) unsigned NOT NULL default '0',
  studentfeedbackurl varchar(255) NOT NULL default '',
  timeopen bigint(10) unsigned NOT NULL,
  timeclose bigint(10) unsigned NOT NULL,
  timelimit bigint(10) NOT NULL default '-1',
  delay1 bigint(10) unsigned NOT NULL,
  delay2 bigint(10) unsigned NOT NULL,
  delay3 bigint(10) NOT NULL default '2',
  `password` varchar(255) NOT NULL default '',
  subnet varchar(255) NOT NULL default '',
  allowresume tinyint(2) unsigned NOT NULL,
  reviewoptions bigint(10) unsigned NOT NULL,
  attemptlimit mediumint(6) unsigned NOT NULL,
  scoremethod smallint(4) unsigned NOT NULL default '1',
  scoreignore tinyint(2) unsigned NOT NULL default '0',
  scorelimit mediumint(6) NOT NULL default '0',
  scoreweighting mediumint(6) NOT NULL default '1',
  sortorder mediumint(6) unsigned NOT NULL,
  clickreporting tinyint(2) unsigned NOT NULL,
  discarddetails tinyint(2) unsigned NOT NULL,
  PRIMARY KEY  (id)
) COMMENT='details of QuizPort quizzes';
ALTER TABLE prefix_quizport_quizzes ADD INDEX prefix_quizquiz_sousou_ix (sourcelocation,sourcefile);
ALTER TABLE prefix_quizport_quizzes ADD INDEX prefix_quizquiz_uni_ix (unitid);

##
## Table structure for table 'quizport_quiz_scores'
##
CREATE TABLE prefix_quizport_quiz_scores (
  id bigint(10) unsigned NOT NULL auto_increment,
  quizid bigint(10) unsigned NOT NULL,
  unumber mediumint(6) unsigned NOT NULL,
  userid bigint(10) unsigned NOT NULL,
  score mediumint(6) unsigned NOT NULL default '0',
  `status` smallint(4) unsigned NOT NULL default '0',
  duration bigint(10) unsigned NOT NULL,
  timemodified bigint(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) COMMENT='details of QuizPort quiz scores';
ALTER TABLE prefix_quizport_quiz_scores ADD INDEX prefix_quizquizscor_qui_ix (quizid);
ALTER TABLE prefix_quizport_quiz_scores ADD INDEX prefix_quizquizscor_use_ix (userid);
ALTER TABLE prefix_quizport_quiz_scores ADD INDEX prefix_quizquizscor_quiuse_ix (quizid,userid);
ALTER TABLE prefix_quizport_quiz_scores ADD UNIQUE INDEX prefix_quizquizscor_quiuseunu_uix (quizid,userid,unumber);

##
## Table structure for table 'quizport_quiz_attempts'
##
CREATE TABLE prefix_quizport_quiz_attempts (
  id bigint(10) unsigned NOT NULL auto_increment,
  quizid bigint(10) unsigned NOT NULL default '0',
  userid bigint(10) unsigned NOT NULL default '0',
  unumber mediumint(6) NOT NULL default '0',
  qnumber mediumint(6) unsigned NOT NULL default '0',
  `status` smallint(4) unsigned NOT NULL default '1',
  penalties mediumint(6) unsigned NOT NULL default '0',
  score mediumint(6) unsigned NOT NULL default '0',
  duration bigint(10) unsigned NOT NULL,
  starttime int(10) unsigned NOT NULL default '0',
  endtime int(10) unsigned NOT NULL default '0',
  resumestart bigint(10) unsigned NOT NULL default '0',
  resumefinish bigint(10) unsigned NOT NULL default '0',
  timestart bigint(10) unsigned NOT NULL default '0',
  timefinish bigint(10) unsigned NOT NULL default '0',
  clickreportid bigint(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) COMMENT='details of QuizPort quiz attempts';
ALTER TABLE prefix_quizport_quiz_attempts ADD INDEX prefix_quizquizatte_qui_ix (quizid);
ALTER TABLE prefix_quizport_quiz_attempts ADD INDEX prefix_quizquizatte_use_ix (userid);
ALTER TABLE prefix_quizport_quiz_attempts ADD INDEX prefix_quizquizatte_cli_ix (clickreportid);
ALTER TABLE prefix_quizport_quiz_attempts ADD INDEX prefix_quizquizatte_quiuseunu_ix (quizid,userid,unumber);

##
## Table structure for table 'quizport_cache'
##
CREATE TABLE prefix_quizport_cache (
  id bigint(10) unsigned NOT NULL auto_increment,
  quizid bigint(10) unsigned NOT NULL default '0',
  slasharguments varchar(1) NOT NULL default '',
  quizport_bodystyles varchar(8) NOT NULL default '',
  quizport_enableobfuscate varchar(1) NOT NULL default '',
  quizport_enableswf varchar(1) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  sourcefile varchar(255) NOT NULL default '',
  sourcetype varchar(255) NOT NULL default '',
  sourcelocation tinyint(2) unsigned NOT NULL,
  sourcelastmodified varchar(255) NOT NULL default '',
  sourceetag varchar(255) NOT NULL default '',
  configfile varchar(255) NOT NULL default '',
  configlocation tinyint(2) unsigned NOT NULL default '0',
  configlastmodified varchar(255) NOT NULL default '',
  configetag varchar(255) NOT NULL default '',
  navigation smallint(4) unsigned NOT NULL default '0',
  title mediumint(6) unsigned NOT NULL default '3',
  stopbutton tinyint(2) unsigned NOT NULL default '0',
  stoptext varchar(255) NOT NULL default '',
  allowpaste smallint(2) unsigned default '0',
  usefilters smallint(2) unsigned default '0',
  useglossary smallint(2) unsigned default '0',
  usemediafilter varchar(255) NOT NULL default '0',
  studentfeedback smallint(4) default '0',
  studentfeedbackurl varchar(255) NOT NULL default '',
  timelimit bigint(10) NOT NULL default '-1',
  delay3 bigint(10) NOT NULL default '-1',
  clickreporting tinyint(2) unsigned NOT NULL default '0',
  content mediumtext NOT NULL,
  md5key varchar(32) NOT NULL default '',
  timemodified bigint(10) unsigned NOT NULL,
  PRIMARY KEY  (id)
) COMMENT='cache for QuizPort quizzes';
ALTER TABLE prefix_quizport_cache ADD INDEX prefix_quizcach_qui_ix (quizid);
ALTER TABLE prefix_quizport_cache ADD UNIQUE INDEX prefix_quizcach_quimd5_uix (quizid,md5key);

##
## Table structure for table 'quizport_conditions'
##
CREATE TABLE prefix_quizport_conditions (
  id bigint(10) unsigned NOT NULL auto_increment,
  quizid bigint(10) unsigned NOT NULL default '0',
  groupid bigint(10) unsigned NOT NULL default '0',
  conditiontype smallint(4) unsigned NOT NULL default '0',
  conditionscore mediumint(6) NOT NULL default '0',
  conditionquizid bigint(10) NOT NULL default '0',
  sortorder smallint(4) unsigned NOT NULL default '0',
  attempttype smallint(4) unsigned NOT NULL default '0',
  attemptcount mediumint(6) NOT NULL default '0',
  attemptduration bigint(10) NOT NULL default '0',
  attemptdelay bigint(10) NOT NULL default '0',
  nextquizid bigint(10) NOT NULL default '0',
  PRIMARY KEY  (id)
) COMMENT='details of QuizPort conditions';

ALTER TABLE prefix_quizport_conditions ADD INDEX prefix_quizcond_gro_ix (groupid);
ALTER TABLE prefix_quizport_conditions ADD INDEX prefix_quizcond_qui_ix (quizid);
ALTER TABLE prefix_quizport_conditions ADD INDEX prefix_quizcond_quigrocon_ix (quizid,groupid,conditiontype);

##
## Table structure for table 'quizport_details'
##
CREATE TABLE prefix_quizport_details (
  id bigint(10) unsigned NOT NULL auto_increment,
  attemptid bigint(10) unsigned NOT NULL default '0',
  details mediumtext,
  PRIMARY KEY  (id)
) COMMENT='raw details (as XML) of QuizPort quiz attempts';
ALTER TABLE prefix_quizport_details ADD INDEX prefix_quizdeta_att_ix (attemptid);

##
## Table structure for table 'quizport_questions'
##
CREATE TABLE prefix_quizport_questions (
  id bigint(10) unsigned NOT NULL auto_increment,
  quizid bigint(10) unsigned NOT NULL default '0',
  `name` text NOT NULL,
  md5key varchar(32) NOT NULL default '',
  `type` smallint(4) unsigned NOT NULL default '0',
  `text` bigint(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (id)
) COMMENT='details of questions used in QuizPort quizzes';

ALTER TABLE prefix_quizport_questions ADD INDEX prefix_quizques_md5_ix (md5key);
ALTER TABLE prefix_quizport_questions ADD INDEX prefix_quizques_qui_ix (quizid);
ALTER TABLE prefix_quizport_questions ADD INDEX prefix_quizques_quimd5_ix (quizid,md5key);

##
## Table structure for table 'quizport_responses'
##
CREATE TABLE prefix_quizport_responses (
  id bigint(10) unsigned NOT NULL auto_increment,
  attemptid bigint(10) unsigned NOT NULL default '0',
  questionid bigint(10) unsigned NOT NULL default '0',
  score mediumint(6) NOT NULL default '0',
  weighting mediumint(6) NOT NULL default '0',
  hints mediumint(6) unsigned NOT NULL default '0',
  clues mediumint(6) unsigned NOT NULL default '0',
  checks mediumint(6) unsigned NOT NULL default '0',
  correct varchar(255) NOT NULL default '',
  wrong varchar(255) NOT NULL default '',
  ignored varchar(255) NOT NULL default '',
  PRIMARY KEY  (id)
) COMMENT='details of responses in QuizPort quiz attempts';
ALTER TABLE prefix_quizport_responses ADD INDEX prefix_quizresp_att_ix (attemptid);
ALTER TABLE prefix_quizport_responses ADD INDEX prefix_quizresp_que_ix (questionid);

##
## Table structure for table 'quizport_strings'
##
CREATE TABLE prefix_quizport_strings (
  id bigint(10) unsigned NOT NULL auto_increment,
  `string` text NOT NULL,
  md5key varchar(32) NOT NULL default '',
  PRIMARY KEY  (id)
) COMMENT='strings used in QuizPort questions and responses';

ALTER TABLE prefix_quizport_strings ADD INDEX prefix_quizstri_md5_ix (md5key);

##
## entries for log_display table, required by print_log (course/lib.php)
## to expand log record "info" field from an id into a QuizPort name
##
INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'editcolumnlists', 'quizport', 'name');
INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'editcondition', 'quizport', 'name');
INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'editquiz', 'quizport', 'name');
INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'editquizzes', 'quizport', 'name');
INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'report', 'quizport', 'name');
INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'submit', 'quizport', 'name');
INSERT INTO prefix_log_display (module, action, mtable, field) VALUES ('quizport', 'view', 'quizport', 'name');

##
## initial config settings for the QuizPort module
##
DELETE FROM prefix_config WHERE `name` LIKE 'quizport_%';
INSERT INTO prefix_config (`name`, `value`) VALUES ('quizport_storedetails', '0');
INSERT INTO prefix_config (`name`, `value`) VALUES ('quizport_enableobfuscate', '1');
INSERT INTO prefix_config (`name`, `value`) VALUES ('quizport_enablecache', '1');
INSERT INTO prefix_config (`name`, `value`) VALUES ('quizport_enableswf', '1');
INSERT INTO prefix_config (`name`, `value`) VALUES ('quizport_maxeventlength', '5');