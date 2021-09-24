<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.2.2/Chart.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<?php
// $chart = new core\chart_bar();
// $chart->add_series($sales);
// $chart->add_series($expenses);
// $chart->set_labels($labels);
// echo $OUTPUT->render($chart);
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This page prints a review of a particular quiz attempt
 *
 * It is used either by the student whose attempts this is, after the attempt,
 * or by a teacher reviewing another's attempt during or afterwards.
 *
 * @package   mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');

$attemptid = required_param('attempt', PARAM_INT);
$page      = optional_param('page', 0, PARAM_INT);
$showall   = optional_param('showall', null, PARAM_BOOL);
$cmid      = optional_param('cmid', null, PARAM_INT);

$url = new moodle_url('/mod/quiz/review.php', array('attempt'=>$attemptid));
if ($page !== 0) {
    $url->param('page', $page);
} else if ($showall) {
    $url->param('showall', $showall);
}
$PAGE->set_url($url);

$attemptobj = quiz_create_attempt_handling_errors($attemptid, $cmid);
$page = $attemptobj->force_page_number_into_range($page);

// Now we can validate the params better, re-genrate the page URL.
if ($showall === null) {
    $showall = $page == 0 && $attemptobj->get_default_show_all('review');
}
$PAGE->set_url($attemptobj->review_url(null, $page, $showall));

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();

// Create an object to manage all the other (non-roles) access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);

$options = $attemptobj->get_display_options(true);

// Check permissions - warning there is similar code in reviewquestion.php and
// quiz_attempt::check_file_access. If you change on, change them all.
if ($attemptobj->is_own_attempt()) {
    if (!$attemptobj->is_finished()) {
        redirect($attemptobj->attempt_url(null, $page));

    } else if (!$options->attempt) {
        $accessmanager->back_to_view_page($PAGE->get_renderer('mod_quiz'),
                $attemptobj->cannot_review_message());
    }

} else if (!$attemptobj->is_review_allowed()) {
    throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noreviewattempt');
}

// Load the questions and states needed by this page.
if ($showall) {
    $questionids = $attemptobj->get_slots();
} else {
    $questionids = $attemptobj->get_slots($page);
}

// Save the flag states, if they are being changed.
if ($options->flags == question_display_options::EDITABLE && optional_param('savingflags', false,
        PARAM_BOOL)) {
    require_sesskey();
    $attemptobj->save_question_flags();
    redirect($attemptobj->review_url(null, $page, $showall));
}

// Work out appropriate title and whether blocks should be shown.
if ($attemptobj->is_own_preview()) {
    navigation_node::override_active_url($attemptobj->start_attempt_url());

} else {
    if (empty($attemptobj->get_quiz()->showblocks) && !$attemptobj->is_preview_user()) {
        $PAGE->blocks->show_only_fake_blocks();
    }
}

// Set up the page header.
$headtags = $attemptobj->get_html_head_contributions($page, $showall);
$PAGE->set_title($attemptobj->review_page_title($page, $showall));
$PAGE->set_heading($attemptobj->get_course()->fullname);

// Summary table start. ============================================================================

// Work out some time-related things.
$attempt = $attemptobj->get_attempt();
$quiz = $attemptobj->get_quiz();
$overtime = 0;

if ($attempt->state == quiz_attempt::FINISHED) {
    if ($timetaken = ($attempt->timefinish - $attempt->timestart)) {
        if ($quiz->timelimit && $timetaken > ($quiz->timelimit + 60)) {
            $overtime = $timetaken - $quiz->timelimit;
            $overtime = format_time($overtime);
        }
        $timetaken = format_time($timetaken);
    } else {
        $timetaken = "-";
    }
} else {
    $timetaken = get_string('unfinished', 'quiz');
}

// Prepare summary informat about the whole attempt.
$summarydata = array();
if (!$attemptobj->get_quiz()->showuserpicture && $attemptobj->get_userid() != $USER->id) {
    // If showuserpicture is true, the picture is shown elsewhere, so don't repeat it.
    $student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));
    $userpicture = new user_picture($student);
    $userpicture->courseid = $attemptobj->get_courseid();
    $summarydata['user'] = array(
        'title'   => $userpicture,
        'content' => new action_link(new moodle_url('/user/view.php', array(
                                'id' => $student->id, 'course' => $attemptobj->get_courseid())),
                          fullname($student, true)),
    );
}

if ($attemptobj->has_capability('mod/quiz:viewreports')) {
    $attemptlist = $attemptobj->links_to_other_attempts($attemptobj->review_url(null, $page,
            $showall));
    if ($attemptlist) {
        $summarydata['attemptlist'] = array(
            'title'   => get_string('attempts', 'quiz'),
            'content' => $attemptlist,
        );
    }
}

// Timing information.
$summarydata['startedon'] = array(
    'title'   => get_string('startedon', 'quiz'),
    'content' => userdate($attempt->timestart),
);

$summarydata['state'] = array(
    'title'   => get_string('attemptstate', 'quiz'),
    'content' => quiz_attempt::state_name($attempt->state),
);

if ($attempt->state == quiz_attempt::FINISHED) {
    $summarydata['completedon'] = array(
        'title'   => get_string('completedon', 'quiz'),
        'content' => userdate($attempt->timefinish),
    );
    $summarydata['timetaken'] = array(
        'title'   => get_string('timetaken', 'quiz'),
        'content' => $timetaken,
    );
}

if (!empty($overtime)) {
    $summarydata['overdue'] = array(
        'title'   => get_string('overdue', 'quiz'),
        'content' => $overtime,
    );
}

// Show marks (if the user is allowed to see marks at the moment).
$grade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);
if ($options->marks >= question_display_options::MARK_AND_MAX && quiz_has_grades($quiz)) {

    if ($attempt->state != quiz_attempt::FINISHED) {
        // Cannot display grade.

    } else if (is_null($grade)) {
        $summarydata['grade'] = array(
            'title'   => get_string('grade', 'quiz'),
            'content' => quiz_format_grade($quiz, $grade),
        );

    } else {
        // Show raw marks only if they are different from the grade (like on the view page).
        if ($quiz->grade != $quiz->sumgrades) {
            $a = new stdClass();
            $a->grade = quiz_format_grade($quiz, $attempt->sumgrades);
            $a->maxgrade = quiz_format_grade($quiz, $quiz->sumgrades);
            $summarydata['marks'] = array(
                'title'   => get_string('marks', 'quiz'),
                'content' => get_string('outofshort', 'quiz', $a),
            );
        }

        // Now the scaled grade.
        $a = new stdClass();
        $a->grade = html_writer::tag('b', quiz_format_grade($quiz, $grade));
        $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
        if ($quiz->grade != 100) {
            $a->percent = html_writer::tag('b', format_float(
                    $attempt->sumgrades * 100 / $quiz->sumgrades, 0));
            $formattedgrade = get_string('outofpercent', 'quiz', $a);
        } else {
            $formattedgrade = get_string('outof', 'quiz', $a);
        }
        $summarydata['grade'] = array(
            'title'   => get_string('grade', 'quiz'),
            'content' => $formattedgrade,
        );
    }
}

// Any additional summary data from the behaviour.
$summarydata = array_merge($summarydata, $attemptobj->get_additional_summary_data($options));

// Feedback if there is any, and the user is allowed to see it now.
$feedback = $attemptobj->get_overall_feedback($grade);
if ($options->overallfeedback && $feedback) {
    $summarydata['feedback'] = array(
        'title'   => get_string('feedback', 'quiz'),
        'content' => $feedback,
    );
}

// Summary table end. ==============================================================================

if ($showall) {
    $slots = $attemptobj->get_slots();
    $lastpage = true;
} else {
    $slots = $attemptobj->get_slots($page);
    $lastpage = $attemptobj->is_last_page($page);
}

$output = $PAGE->get_renderer('mod_quiz');
// echo "<pre>";
// print_r($output);
// echo "</pre>";
// die();
// Arrange for the navigation to be displayed.
$navbc = $attemptobj->get_navigation_panel($output, 'quiz_review_nav_panel', $page, $showall);
$regions = $PAGE->blocks->get_regions();
$PAGE->blocks->add_fake_block($navbc, reset($regions));
// echo "<pre>";
// // print_r($navbc->content);
// echo "</pre>";
?>
<div class="container">
  <div class="myChart-main">
    <canvas id="myChart"></canvas>
  </div>
</div>
<?php
// echo "<pre>";
// print_r($navbc->content);
// echo "</pre>";
// die;
 echo $output->review_page($attemptobj, $slots, $page, $showall, $lastpage, $options, $summarydata);

// echo "<a href='' class='qnbutton incorrect free btn thispage' id='quiznavbutton1'>Hiiiiiiiiiiiiiiiii<a>";

// Trigger an event for this review.
$attemptobj->fire_attempt_reviewed_event();
?>
<style type="text/css">

select {
    -webkit-appearance: none;
    -moz-appearance: none;
    text-indent: 1px;
    text-overflow: '';
}
.bg-green.popup-box .modal-header{
    background-color: #52b254;
}
.bg-green.popup-box .que-box{
    background-color: #dcffdc;
}
.bg-green.popup-box .que-box .no{
    background-color: #52b254;
}
.bg-green.popup-box .form-control{
    border-color: #52b254;
}
.bg-green .modal-footer .fa-star{
   color: #52b254;
}
.bg-green.popup-box .btn{
   background-color: #52b254;
}
.radio-label input:checked ~ .checkmark {
    background-color: #fff;
}

img {
    max-width: 100%;
    border: 0;
    -ms-interpolation-mode: bicubic;
    vertical-align: middle;
}
a img, a:hover img, img, a input {
    text-decoration: none;
}
.constractor {
    background-color:#12a7db !important;
}
.sconstrctor {
    background-color: #c5f7ff !important;
}
.no-1 {
    height: 40px;
    width: 40px;
    display: inline-block;
    text-align: center;
    border-radius: 50%;
    line-height: 40px;
    color: #fff;
    margin-right: 15px;
    background-color: #0295ce;
}
[data-region="blocks-column"] {
    width: 200px !important;
    float: right;
}
#region-main-settings-menu.has-blocks, #region-main.has-blocks {
    display: inline-block;
    width: calc(100% - 215px) !important;
}

input, select, textarea, button, .btn {
    font-family: 'Roboto', sans-serif;
    direction: ltr;
    color: #3e3e3e;
    font-size: 14px;
    line-height: 20px;
    padding: 8px 10px;
    margin: 0em;
    margin: 0px;
    border: 1px solid #cecece;
    transition: all 0.4s ease-out;
    border-radius: 3px;
    box-sizing: border-box;
    outline: none;
}
/*.formulation
{
    border:1px solid #9e9e9e;
}*/
.modal-body {
   padding: 40px 40px !important;
    display: inline-block;
    width: 100%;
    background-color: #fff;
}
.form-control {
    display: inline !important;   
}
.popup-box .form-control {
    border-color: #12a7db;
}
p {
    margin: 0px;
    padding-bottom: 15px;
    font-size: 20px;
    line-height: 35px;
    /*font-weight: 500;*/
}
.que-box .no {
    height: 40px;
    width: 40px;
    display: inline-block;
    text-align: center;
    border-radius: 50%;
    line-height: 40px;
    color: #fff;
    margin-right: 15px;
    background-color: #d33924;
}

.correct input[type="checkbox"]:checked + div .flex-fill .circle{
    background-color: #808080;
    color: #fff;
}
section#region-main {
    background: #f6f6f6;
}
/*.answer input[type="checkbox"]{
    opacity: 0;
}*/
/*input#yui_3_17_2_1_1630567536098_33
{
   opacity: 0;  
}*/
label input[type='checkbox'] {
  /*  display: none;*/
}
.circle {
    height: 50px;
    width: 50px;
    display: inline-block;
    text-align: center;
    border: 1px solid #828282;
    border-radius: 50%;
    line-height: 47px;
    color: #fff;
    margin-right: 15px;
    color: #000;
    font-size: 20px;
    font-weight: bold;
    background-color: transparent;
}
input[type="checkbox"]:checked + label .circle {
    background: #808080;
    color: #fff;
}

.que .formulation {
    color: #001a1e;
    background-color: #fff !important; 
     border-color: #fff !important; 
     margin-right: 100px;
     margin-bottom: 0px;
}
body
{
    background-color: #fafafa !important;
    color: #000;
}
.popup-box .modal-header img.left-arrow{
    width: 24px;
    margin-right: 10px;
}
.modal-header {
    background-color: #f94623;
    color: #fff;
    padding: 15px 20px;
    border-radius: 0px;
}
.que.multichoice .answer div.r0, .que.multichoice .answer div.r1 {
    display: INLINE-BLOCK;
    margin: .25rem 0;
    /* align-items: flex-start; */
    WIDTH: 50%;
    float: left;
    margin: 20px 0px 0px 0px !important;
    min-height: 120px;

}
.que-box{
     background-color: #ffe8e6;
    padding: 5px 15px;
    font-size: 20px;
    float: left;
    width: 100%;
}
.value-text {
    float: left;
    padding-top: 5px;
}
.que-box .value {
    display: inline-block;
    text-align: center;
    margin-left: 12px;
    font-size: 18px;
}
.que-box .value span {
    display: block;
}
.que-box .value span + span {
    border-top: 1px solid #bcaba9;
}
.hydrogen-oxygen-box input{
  display: inline-block;
  width: 40px !important;
  top: 15px;
  position: relative;
  border: 2px solid #f95332;
}
.radio-label {
    display: block;
    position: relative;
    padding-left: 45px;
    margin-bottom: 12px;
    cursor: pointer;
    font-size: 22px;
    padding-top: 2px;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
  }
  
  /* Hide the browser's default radio button */
  .radio-label input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
  }
  
  /* Create a custom radio button */
  .radio-label .checkmark {
    position: absolute;
    top: 0;
    left: 0;
    height: 30px;
    width: 30px;
    opacity: 1;
    background-color:transparent;
    border-radius: 50%;
    border: 1px solid #4d4d4d;
  }
   .radio-label input:checked ~ .checkmark {
    background-color: #fff;
  }
  
  /* Create the indicator (the dot/circle - hidden when not checked) */
  .checkmark:after {
    content: "";
    position: absolute;
    display: none;
  }
  
  /* Show the indicator (dot/circle) when checked */
  .radio-label input:checked ~ .checkmark:after {
    display: block;
  }
  
  /* Style the indicator (dot/circle) */
  .radio-label .checkmark:after {
    top: 5px;
    left: 5px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #4d4d4d;
  }
.checkbox-label input:checked ~ .circle {
    background: #808080;
    color: #fff;
}
ul.ans-list{
    display: inline-block;
    width: 100%;    
}
ul.ans-list li{
    width: 50%;
    float: left;
    margin: 20px 0px;
    list-style: none;
}

.styled-checkbox {
    position: absolute;
    opacity: 0;
  }
  .styled-checkbox + label {
    position: relative;
    cursor: pointer;
    padding: 0;
    font-size: 20px;
  }
.form-control:disabled, .form-control[readonly] {
    background-color: #fff !important;
    opacity: 1;
}
    html {
    font-size: 15px;
    font-family: sans-serif;
}



.input-numeric-container {
    background: #fff;
   /* padding: 1em;
    margin: 1em auto;*/
    max-width: 245px;
    display: inline-block;
}


.input-numeric {
    width: 100%;
    padding: 0.8em 0.2em;
    margin-bottom: 0.8em;
    box-sizing: border-box;
    border: 1px solid silver;
    outline-color: #4CAF50;
}

.table-numeric {
    width: 100%;
    border-collapse: collapse;
}

.table-numeric td {
    vertical-align: top;
    text-align: center;
    width: 33.33333333333%;
    border: 0;
}

.table-numeric button {
    position: relative;
    cursor: pointer;
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 0.6em 0.3em;
    font-size: 1em;
    border-radius: 0.1em;
    outline: none;
    user-select: none;
}

.table-numeric button:active {
    top: 2px;
}

.key {
    background: #fff;
    border: 1px solid #d8d6d6;
    width: 50px !important;
}

.key-del {
    background: #FF9800;
    border: 1px solid #ca7800;
    color: #fff;
}

.key-clear {
    background: #E91E63;
    border: 1px solid #c70a4b;
    color: #fff;
}

button[disabled] {
    opacity: 0.5;
    cursor: no-drop;
}

[data-numeric="hidden"] .table-numeric {
    display: none;
    background-color: #fff;
    width: 165px;
    position: absolute;
}
select::-ms-expand
{
     display: none;
}
.bg-neonCarrot.popup-box .modal-header{
    background-color: #ff9e3e;
}
.bg-neonCarrot.popup-box .que-box{
    background-color: #ffe9d7;
}
.bg-neonCarrot.popup-box .que-box .no{
    background-color: #ff9e3e;
}
.bg-neonCarrot.popup-box .form-control{
    border-color: #ff9e3e;
}
.bg-neonCarrot .modal-footer .fa-star{
   color: #ff9e3e;
}
.bg-neonCarrot.popup-box .btn{
   background-color: #ff9e3e;
}
.fa-arrow-left {
    color: #fff !important;
}
.select-box p {
    display: inline-block;
    padding: 10px;
}
ul.hydrogen-list li input {
    border: 2px solid #f95332 !important;
    width: 70px !IMPORTANT;
    height: 30px;
}
ul.hydrogen-list li span {
    font-size: 22px;
    min-width: 55px;
    display: inline-block;
}
.ablock.form-inline {
    display: none;
}
.modal-body .hydrogen-oxygen-box span select
{
   width: 40px;
    top: 15px;
    position: relative;
    border: 2px solid #f95332; 
}
.modal-body .line-height-60 span select
{
    border: none;
    padding: 0px 5px;
    border-radius: 0px;
    border-bottom: 1px solid #f7931e;
    width: 100px;
}
.line-height-60 span select:focus
{
    border: none !important;
    padding: 0px 5px !important;
    border-radius: 0px !important;
    border-bottom: 1px solid #f7931e !important;
    width: 100px !important;
    box-shadow: none !important;
}
.line-height-60 {
    line-height: 60px;
}
.hydrogen-list li span select
{
    border: 2px solid #f95332;
    width: 70px;
    height: 30px;
    padding-top: 0px;
}
.hydrogen-list li span select:focus
{
   border: 2px solid #f95332 ;
    width: 70px;
    height: 30px ;
     box-shadow: none !important;
}
.que.truefalse .answer div.r0, .que.truefalse .answer div.r1 {
   padding: .3em;
    font-size: 15px;
    display: inline-block;
    margin-right: 11em;
    margin-left: 70px;
    transform: scale(1.8);
}
.que.truefalse .answer div.r0, .que.truefalse .answer div.r1 :focus {
     box-shadow: none !important;
    }
     .que.truefalse .answer div.r0 :focus {
     box-shadow: none !important;
    }
.prompt {
    display: none;
}
/*************Hide**************************/
header#page-header {
    opacity: 0;
    display: none;
}
.info {
    display: none;
}
/*.card-body.p-3 {
    display: none;
}
div#page {
    padding-top: 30px;
}*/
/*section.d-print-none {
    display: none;
}*/
    .mt-5.mb-1.activity-navigation.container-fluid {
    display: none;
}

/***************end hide*******************************/
  input[type='radio']:after {
        width: 15px;
        height: 15px;
        border-radius: 15px;
        top: -2px;
        left: -1px;
        position: relative;
        background-color: #d1d3d1;
        content: '';
        display: inline-block;
        visibility: visible;
        border: 1px solid #4d4d4d
    }
    input[type='radio']:checked:after {
        width: 15px;
        height: 15px;
        border-radius: 15px;
        top: -2px;
        left: -1px;
        position: relative;
        background-color:#4d4d4d;
        content: '';
        display: inline-block;
        visibility: visible;
        border: 1px solid #4d4d4d
    }
/*ques 11*/
.modal-body small{
  position: relative;
   top: -7px;
}
.table td, .table th{
  padding: 5px;
  font-weight: 400;
}
.bondTable{
  max-width: 340px;
}
.average-bond-table thead{
  font-size: 22px;
  font-weight: bold;
  text-align: center;
}
.average-bond-table.table thead th, .average-bond-table.table-bordered td, .average-bond-table.table-bordered th{
   border-color: #9d9d9d;
}
.average-bond-table.table-bordered td{
  font-size: 20px;
  color: #000;
  font-weight: bold;
  text-align: center;
}
.average-bond-table.table-bordered td select{
  width: 80px;
  height:34px;
  border: 2px solid #f94c2a;
}
select:focus {
    box-shadow: none !important;
    }
/*********************************************Popup 11 css***/
.box-standing-balcony select{
  border-color: #fa7459 !important;
}
.box-standing-balcony p{
  line-height: 60px;
}
.btn.btn-border{
  border-radius: 50px;
  padding: 11px 40px;
}
.popup-box .form-control {
    display: inline-block;
}
.box-standing-balcony p span select{
    border: none;
    /*padding: 0px 5px;*/
    border-radius: 0px;
    border-bottom: 1px solid;
    width: 100px;
}
.popup-box .btn {
    padding: 8px 40px;
    text-decoration: none;
    text-transform: capitalize;
    background-color: #f74523;
    border-radius: 0px;
    color: #FFF;
    font-size: 18px;
    font-weight: 400;
}
.btn.btn-border{
  border-radius: 50px;
  padding: 11px 40px;
}
.box-magnesium-plates select {
    padding-top: 0px;
    border: 2px solid #f95332;
    width: 70px;
    height: 30px;
}

.correct-answer{
  font-size: 23px;
  color: #7cb342;
  margin-top: 20px;
}
.box-statement-best .checkbox-label{
  padding-left: 65px;
}
.box-statement-best ul.ans-list li .circle{
  position: absolute;
  left: 0px;
}
.checkbox-label {
    display: block;
    position: relative;
    padding-left: 0px;
    margin-bottom: 12px;
    cursor: pointer;
    font-size: 22px;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}
/*.answer input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}*/

.btn-primary {
    background: red;
    color: #fff;
}
.correct input[type="checkbox"]:checked + div .flex-fill .circle {
    background-color: #7CB442;
    color: #fff;
}
.incorrect input[type="checkbox"]:checked + div .flex-fill .circle{
    background-color: #E62E2D;
    color: #fff;
}
.icon.fa-check, .icon.fa-remove{
    display: none;
}
.que.multichoice .answer div.r0 input, .que.multichoice .answer div.r1 input{
    opacity: 0;
}
.que .formulation, .que .outcome, .que .comment{
    padding: 0px;
}
.que .outcome, .que .comment{
    margin-right: 100px;
    background-color: #fff;
}
.answer{
    padding: 40px;
}
/*.feedback .rightanswer{
    display: none;
}*/
.feedback{
    padding: 10px 20px;
}
.answercontainer.readonly {
    padding: 0px 40px;
}
.rightanswer{
   color: #7CB442;
}
.rightanswer label .d-data
{
 display: none;
}
.rightanswer label .circle
{
 border: none;
 margin-right: 0px;
 width: 15px;
 color: #7CB442;
}

.path-mod-quiz #mod_quiz_navblock .qnbutton {
    text-decoration: none;
    font-size: 14px;
    line-height: 20px;
    font-weight: 400;
    background-color: #fff;
    background-image: none;
    height: 40px;
    width: 30px;
    border-radius: 3px;
    border: 0;
    overflow: visible;
    margin: 0 6px 6px 0;
    display: inline-block;
    float: none;
}
.qn_buttons.clearfix.allquestionsononepage {
    padding: 15px;
    width: 100%;
    position: fixed;
    left: 0px;
    bottom: 0;
    background-color: #fff;
    text-align: center;
}
/*.generalfeedback img
{
   position: relative;  
}*/
.rightanswer {
    color: #7CB442;
    width: 100%;
}
.feedback {
     display: flex; 
     -webkit-flex-flow: row wrap; 
     flex-flow: row wrap; 
}
.generalfeedback {
    order: 2;
}
.que .outcome hr, .que .comment hr {
    border-top-color: #F6F6F6;
    width: 842px;
    border-block-width: 4px;
}
.d-block {
    display: unset;
}
ul.video-suggestion li {
    float: left;
    margin-right: 20px;
    list-style: none;
}
ul.video-suggestion {
    padding-top: 20px;
    display: inline-block;
    width: 100%;
}
.mediaplugin, .mediaplugin video {
    width: 140px;
    max-width: 100%;
}

  .modal-body .drop  {
    width: 35px !important;
    height: 43px !important;
    border: 2px solid #f94623 !important;
    margin: 2px;
}
.draggrouphomes1 .draghome{
    background-color: #f94623 !important;
    color: #fff;
    font-size: 25px;
    position: relative;
    bottom: 80px;
    left: 39px;
}
span.dragInPara {
    position: relative;
    left: 192px;
    top: 33px;
    font-size: 20px !important;
}
.container {
  width: 80%;
  margin: 15px auto;
  padding-top: 60px;
}
</style> 


<script type="text/javascript">
    var arr1 = [];
    var arr2 = [];
    var arr3 = [];
    var arr4 = [];

$( '.correct').each(function() {
var correct = $(this).attr('data-quiz-page'); 
        if(correct){
           arr1[correct]=correct; 
          // arr2.push(incorrect);  
        }
   

});

$( '.incorrect').each(function() {
var incorrect = $(this).attr('data-quiz-page'); 
        if(incorrect){
           arr2[incorrect] =incorrect;
        }
   

});
      
$( '.partiallycorrect').each(function() {
var partiallycorrect = $(this).attr('data-quiz-page'); 
        if(partiallycorrect){
            arr3[partiallycorrect] =partiallycorrect;
           // arr3.push(partiallycorrect);   
        }
   

});
$( '.notanswered').each(function() {
var notanswered = $(this).attr('data-quiz-page'); 
        if(notanswered){
            arr4[notanswered] =notanswered;
           //arr4.push(notanswered);   
        }
   

});

// var newArray = $.merge([], (arr1) ,(arr2) ,(arr3), arr4);
var newArray1 = $.merge( $.merge( [], arr1 ),arr2);
var newArray2 = $.merge( $.merge( [], newArray1 ),arr3);
var newArray3 = $.merge( $.merge( [], newArray2 ),arr4);

newArray3.sort(function(a, b){
  return parseInt(a)- parseInt(b);
});

newArray3 = newArray3.filter(function( element ) {
   return element !== undefined;
});
newArray3 =newArray3.slice(1);
 //console.log(newArray3);
    var ctx = document.getElementById("myChart").getContext('2d');
var myChart = new Chart(ctx, {
  type: 'bar',
  data: {
    labels:newArray3,
    datasets: [{
      label: 'Correct',
      data: arr1,
      backgroundColor: "rgb(124,180,66)"
    }, {
      label: 'Incorrect',
      data: arr2,
      backgroundColor: "rgb(230,46,45)"
    },
     {
      label: 'Not answered',
      data: arr3,
      backgroundColor: "rgb(147,39,143)"
    },
    {
      label: 'Suggested time',
      data: arr4,
      backgroundColor: "rgb(102,102,102)"
    }]
  }
});
</script>