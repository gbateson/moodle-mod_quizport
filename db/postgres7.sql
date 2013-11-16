CREATE TABLE prefix_quizport (
    id BIGSERIAL PRIMARY KEY,
    course BIGINT NOT NULL DEFAULT '0',
    name VARCHAR(255) NOT NULL DEFAULT '',
    timecreated BIGINT NOT NULL DEFAULT '0',
    timemodified BIGINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport IS 'details of QuizPort activities';
CREATE INDEX prefix_quizport_cou_ix ON prefix_quizport (course);

CREATE TABLE prefix_quizport_units (
    id BIGSERIAL PRIMARY KEY,
    parenttype SMALLINT NOT NULL DEFAULT '0',
    parentid BIGINT NOT NULL DEFAULT '0',
    entrycm BIGINT NOT NULL DEFAULT '0',
    entrygrade INTEGER NOT NULL DEFAULT '100',
    entrypage SMALLINT NOT NULL DEFAULT '0',
    entrytext TEXT NOT NULL,
    entryoptions BIGINT NOT NULL DEFAULT '0',
    exitpage SMALLINT NOT NULL DEFAULT '0',
    exittext TEXT NOT NULL,
    exitoptions BIGINT NOT NULL DEFAULT '0',
    exitcm BIGINT NOT NULL DEFAULT '0',
    exitgrade INTEGER NOT NULL DEFAULT '0',
    showpopup SMALLINT NOT NULL DEFAULT '0',
    popupoptions VARCHAR(255) NOT NULL DEFAULT '',
    timeopen BIGINT NOT NULL DEFAULT '0',
    timeclose BIGINT NOT NULL DEFAULT '0',
    timelimit BIGINT NOT NULL DEFAULT '0',
    delay1 BIGINT NOT NULL DEFAULT '0',
    delay2 BIGINT NOT NULL DEFAULT '0',
    password VARCHAR(255) NOT NULL DEFAULT '',
    subnet VARCHAR(255) NOT NULL DEFAULT '',
    allowresume SMALLINT NOT NULL DEFAULT '1',
    allowfreeaccess INTEGER NOT NULL DEFAULT '0',
    attemptlimit INTEGER NOT NULL DEFAULT '0',
    attemptgrademethod SMALLINT NOT NULL DEFAULT '0',
    grademethod SMALLINT NOT NULL DEFAULT '1',
    gradeignore SMALLINT NOT NULL DEFAULT '0',
    gradelimit INTEGER NOT NULL DEFAULT '100',
    gradeweighting INTEGER NOT NULL DEFAULT '100'
);
COMMENT ON TABLE prefix_quizport_units IS 'details of QuizPort units';
CREATE UNIQUE INDEX prefix_quizunit_parpar_uix ON prefix_quizport_units (parenttype, parentid);

CREATE TABLE prefix_quizport_unit_grades (
    id BIGSERIAL PRIMARY KEY,
    parenttype SMALLINT NOT NULL DEFAULT '0',
    parentid BIGINT NOT NULL DEFAULT '0',
    userid BIGINT NOT NULL DEFAULT '0',
    grade INTEGER NOT NULL DEFAULT '0',
    status SMALLINT NOT NULL DEFAULT '0',
    duration BIGINT NOT NULL DEFAULT '0',
    timemodified BIGINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport_unit_grades IS 'details of QuizPort activity grades';
CREATE INDEX prefix_quizunitgrad_parpar_ix ON prefix_quizport_unit_grades (parenttype, parentid);
CREATE INDEX prefix_quizunitgrad_use_ix ON prefix_quizport_unit_grades (userid);

CREATE TABLE prefix_quizport_unit_attempts (
    id BIGSERIAL PRIMARY KEY,
    unitid BIGINT NOT NULL DEFAULT '0',
    unumber INTEGER NOT NULL DEFAULT '0',
    userid BIGINT NOT NULL DEFAULT '0',
    grade INTEGER NOT NULL DEFAULT '0',
    status SMALLINT NOT NULL DEFAULT '0',
    duration BIGINT NOT NULL DEFAULT '0',
    timemodified BIGINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport_unit_attempts IS 'details of the grades for attempts at QuizPort units';
CREATE INDEX prefix_quizunitatte_uni_ix ON prefix_quizport_unit_attempts (unitid);
CREATE INDEX prefix_quizunitatte_use_ix ON prefix_quizport_unit_attempts (userid);
CREATE UNIQUE INDEX prefix_quizunitatte_useuniunu_uix ON prefix_quizport_unit_attempts (userid, unitid, unumber);

CREATE TABLE prefix_quizport_quizzes (
    id BIGSERIAL PRIMARY KEY,
    unitid BIGINT NOT NULL DEFAULT '0',
    name VARCHAR(255) NOT NULL DEFAULT '',
    sourcefile VARCHAR(255) NOT NULL DEFAULT '',
    sourcetype VARCHAR(255) NOT NULL DEFAULT '',
    sourcelocation SMALLINT NOT NULL DEFAULT '0',
    configfile VARCHAR(255) NOT NULL DEFAULT '',
    configlocation SMALLINT NOT NULL DEFAULT '0',
    outputformat VARCHAR(255) NOT NULL DEFAULT '',
    navigation INTEGER NOT NULL DEFAULT '0',
    title INTEGER NOT NULL DEFAULT '3',
    stopbutton SMALLINT NOT NULL DEFAULT '0',
    stoptext VARCHAR(255) NOT NULL DEFAULT '',
    allowpaste SMALLINT NOT NULL DEFAULT '0',
    usefilters SMALLINT NOT NULL DEFAULT '0',
    useglossary SMALLINT NOT NULL DEFAULT '0',
    usemediafilter VARCHAR(255) NOT NULL DEFAULT '',
    studentfeedback SMALLINT NOT NULL DEFAULT '0',
    studentfeedbackurl VARCHAR(255) NOT NULL DEFAULT '',
    timeopen BIGINT NOT NULL DEFAULT '0',
    timeclose BIGINT NOT NULL DEFAULT '0',
    timelimit BIGINT NOT NULL DEFAULT '-1',
    delay1 BIGINT NOT NULL DEFAULT '0',
    delay2 BIGINT NOT NULL DEFAULT '0',
    delay3 BIGINT NOT NULL DEFAULT '2',
    password VARCHAR(255) NOT NULL DEFAULT '',
    subnet VARCHAR(255) NOT NULL DEFAULT '',
    allowresume SMALLINT NOT NULL DEFAULT '0',
    reviewoptions BIGINT NOT NULL DEFAULT '0',
    attemptlimit INTEGER NOT NULL DEFAULT '0',
    scoremethod SMALLINT NOT NULL DEFAULT '1',
    scoreignore SMALLINT NOT NULL DEFAULT '0',
    scorelimit INTEGER NOT NULL DEFAULT '1',
    scoreweighting INTEGER NOT NULL DEFAULT '-1',
    sortorder INTEGER NOT NULL DEFAULT '0',
    clickreporting SMALLINT NOT NULL DEFAULT '0',
    discarddetails SMALLINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport_quizzes IS 'details of QuizPort quizzes';
CREATE INDEX prefix_quizquiz_sousou_ix ON prefix_quizport_quizzes (sourcelocation, sourcefile);
CREATE INDEX prefix_quizquiz_uni_ix ON prefix_quizport_quizzes (unitid);

CREATE TABLE prefix_quizport_quiz_scores (
    id BIGSERIAL PRIMARY KEY,
    quizid BIGINT NOT NULL DEFAULT '0',
    unumber INTEGER NOT NULL DEFAULT '0',
    userid BIGINT NOT NULL DEFAULT '0',
    score INTEGER NOT NULL DEFAULT '0',
    status SMALLINT NOT NULL DEFAULT '0',
    duration BIGINT NOT NULL DEFAULT '0',
    timemodified BIGINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport_quiz_scores IS 'details of QuizPort quiz scores';
CREATE INDEX prefix_quizquizscor_qui_ix ON prefix_quizport_quiz_scores (quizid);
CREATE INDEX prefix_quizquizscor_use_ix ON prefix_quizport_quiz_scores (userid);
CREATE INDEX prefix_quizquizscor_quiuse_ix ON prefix_quizport_quiz_scores (quizid, userid);
CREATE UNIQUE INDEX prefix_quizquizscor_quiuseunu_uix ON prefix_quizport_quiz_scores (quizid, userid, unumber);

CREATE TABLE prefix_quizport_quiz_attempts (
    id BIGSERIAL PRIMARY KEY,
    quizid BIGINT NOT NULL DEFAULT '0',
    userid BIGINT NOT NULL DEFAULT '0',
    unumber INTEGER NOT NULL DEFAULT '0',
    qnumber INTEGER NOT NULL DEFAULT '0',
    status SMALLINT NOT NULL DEFAULT '1',
    penalties INTEGER NOT NULL DEFAULT '0',
    score INTEGER NOT NULL DEFAULT '0',
    duration BIGINT NOT NULL DEFAULT '0',
    starttime BIGINT NOT NULL DEFAULT '0',
    endtime BIGINT NOT NULL DEFAULT '0',
    resumestart BIGINT NOT NULL DEFAULT '0',
    resumefinish BIGINT NOT NULL DEFAULT '0',
    timestart BIGINT NOT NULL DEFAULT '0',
    timefinish BIGINT NOT NULL DEFAULT '0',
    clickreportid BIGINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport_quiz_attempts IS 'details of QuizPort quiz attempts';
CREATE INDEX prefix_quizquizatte_qui_ix ON prefix_quizport_quiz_attempts (quizid);
CREATE INDEX prefix_quizquizatte_use_ix ON prefix_quizport_quiz_attempts (userid);
CREATE INDEX prefix_quizquizatte_cli_ix ON prefix_quizport_quiz_attempts (clickreportid);
CREATE INDEX prefix_quizquizatte_quiuseunu_ix ON prefix_quizport_quiz_attempts (quizid, userid, unumber);

CREATE TABLE prefix_quizport_cache (
    id BIGSERIAL PRIMARY KEY,
    quizid BIGINT NOT NULL DEFAULT '0',
    slasharguments VARCHAR(1) NOT NULL DEFAULT '',
    quizport_bodystyles VARCHAR(8) NOT NULL DEFAULT '',
    quizport_enableobfuscate VARCHAR(1) NOT NULL DEFAULT '',
    quizport_enableswf VARCHAR(1) NOT NULL DEFAULT '',
    name VARCHAR(255) NOT NULL DEFAULT '',
    sourcefile VARCHAR(255) NOT NULL DEFAULT '',
    sourcetype VARCHAR(255) NOT NULL DEFAULT '',
    sourcelocation SMALLINT NOT NULL DEFAULT '0',
    sourcelastmodified VARCHAR(255) NOT NULL DEFAULT '',
    sourceetag VARCHAR(255) NOT NULL DEFAULT '',
    configfile VARCHAR(255) NOT NULL DEFAULT '',
    configlocation SMALLINT NOT NULL DEFAULT '0',
    configlastmodified VARCHAR(255) NOT NULL DEFAULT '',
    configetag VARCHAR(255) NOT NULL DEFAULT '',
    navigation SMALLINT NOT NULL DEFAULT '0',
    title INTEGER NOT NULL DEFAULT '3',
    stopbutton SMALLINT NOT NULL DEFAULT '0',
    stoptext VARCHAR(255) NOT NULL DEFAULT '',
    allowpaste SMALLINT DEFAULT '0',
    usefilters SMALLINT DEFAULT '0',
    useglossary SMALLINT DEFAULT '0',
    usemediafilter VARCHAR(255) NOT NULL DEFAULT '0',
    studentfeedback SMALLINT DEFAULT '0',
    studentfeedbackurl VARCHAR(255) NOT NULL DEFAULT '',
    timelimit BIGINT NOT NULL DEFAULT '-1',
    delay3 BIGINT NOT NULL DEFAULT '-1',
    clickreporting SMALLINT NOT NULL DEFAULT '0',
    content TEXT NOT NULL,
    md5key VARCHAR(32) NOT NULL DEFAULT '',
    timemodified BIGINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport_cache IS 'cache for QuizPort quizzes';
CREATE INDEX prefix_quizcach_qui_ix ON prefix_quizport_cache (quizid);
CREATE UNIQUE INDEX prefix_quizcach_quimd5_uix ON prefix_quizport_cache (quizid, md5key);

CREATE TABLE prefix_quizport_conditions (
    id BIGSERIAL PRIMARY KEY,
    quizid BIGINT NOT NULL DEFAULT '0',
    groupid BIGINT NOT NULL DEFAULT '0',
    conditiontype SMALLINT NOT NULL DEFAULT '0',
    conditionscore INTEGER NOT NULL DEFAULT '0',
    conditionquizid BIGINT NOT NULL DEFAULT '0',
    sortorder SMALLINT NOT NULL DEFAULT '0',
    attempttype SMALLINT NOT NULL DEFAULT '0',
    attemptcount INTEGER NOT NULL DEFAULT '0',
    attemptduration BIGINT NOT NULL DEFAULT '0',
    attemptdelay BIGINT NOT NULL DEFAULT '0',
    nextquizid BIGINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport_conditions IS 'details of QuizPort conditions';
CREATE INDEX prefix_quizcond_gro_ix ON prefix_quizport_conditions (groupid);
CREATE INDEX prefix_quizcond_qui_ix ON prefix_quizport_conditions (quizid);
CREATE INDEX prefix_quizcond_quigrocon_ix ON prefix_quizport_conditions (quizid, groupid, conditiontype);

CREATE TABLE prefix_quizport_details (
    id BIGSERIAL PRIMARY KEY,
    attemptid BIGINT NOT NULL DEFAULT '0',
    details TEXT
);
COMMENT ON TABLE prefix_quizport_details IS 'raw details (as XML) of QuizPort quiz attempts';
CREATE INDEX prefix_quizdeta_att_ix ON prefix_quizport_details (attemptid);

CREATE TABLE prefix_quizport_questions (
    id BIGSERIAL PRIMARY KEY,
    quizid BIGINT NOT NULL DEFAULT '0',
    name TEXT NOT NULL,
    md5key VARCHAR(32) NOT NULL DEFAULT '',
    type SMALLINT NOT NULL DEFAULT '0',
    text BIGINT NOT NULL DEFAULT '0'
);
COMMENT ON TABLE prefix_quizport_questions IS 'details of questions used in QuizPort quizzes';
CREATE INDEX prefix_quizques_md5_ix ON prefix_quizport_questions (md5key);
CREATE INDEX prefix_quizques_qui_ix ON prefix_quizport_questions (quizid);
CREATE INDEX prefix_quizques_quimd5_ix ON prefix_quizport_questions (quizid, md5key);

CREATE TABLE prefix_quizport_responses (
    id BIGSERIAL PRIMARY KEY,
    attemptid BIGINT NOT NULL DEFAULT '0',
    questionid BIGINT NOT NULL DEFAULT '0',
    score INTEGER NOT NULL DEFAULT '0',
    weighting INTEGER NOT NULL DEFAULT '0',
    hints INTEGER NOT NULL DEFAULT '0',
    clues INTEGER NOT NULL DEFAULT '0',
    checks INTEGER NOT NULL DEFAULT '0',
    correct VARCHAR(255) NOT NULL DEFAULT '',
    wrong VARCHAR(255) NOT NULL DEFAULT '',
    ignored VARCHAR(255) NOT NULL DEFAULT ''
);
COMMENT ON TABLE prefix_quizport_responses IS 'details of responses in QuizPort quiz attempts';
CREATE INDEX prefix_quizresp_att_ix ON prefix_quizport_responses (attemptid);
CREATE INDEX prefix_quizresp_que_ix ON prefix_quizport_responses (questionid);

CREATE TABLE prefix_quizport_strings (
    id BIGSERIAL PRIMARY KEY,
    string TEXT NOT NULL,
    md5key VARCHAR(32) NOT NULL DEFAULT ''
);
COMMENT ON TABLE prefix_quizport_strings IS 'strings used in QuizPort questions and responses';
CREATE INDEX prefix_quizstri_md5_ix ON prefix_quizport_strings (md5key);

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
DELETE FROM prefix_config WHERE "name" LIKE 'quizport_%';
INSERT INTO prefix_config ("name", "value") VALUES ('quizport_storedetails', '0');
INSERT INTO prefix_config ("name", "value") VALUES ('quizport_enableobfuscate', '1');
INSERT INTO prefix_config ("name", "value") VALUES ('quizport_enablecache', '1');
INSERT INTO prefix_config ("name", "value") VALUES ('quizport_enableswf', '1');
INSERT INTO prefix_config ("name", "value") VALUES ('quizport_maxeventlength', '5');