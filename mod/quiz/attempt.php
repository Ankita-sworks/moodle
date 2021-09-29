<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>


<?php

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
 * This script displays a particular page of a quiz attempt that is in progress.
 *
 * @package   mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

// Look for old-style URLs, such as may be in the logs, and redirect them to startattemtp.php.
if ($id = optional_param('id', 0, PARAM_INT)) {
    redirect($CFG->wwwroot . '/mod/quiz/startattempt.php?cmid=' . $id . '&sesskey=' . sesskey());
} else if ($qid = optional_param('q', 0, PARAM_INT)) {
    if (!$cm = get_coursemodule_from_instance('quiz', $qid)) {
        print_error('invalidquizid', 'quiz');
    }
    redirect(new moodle_url('/mod/quiz/startattempt.php',
            array('cmid' => $cm->id, 'sesskey' => sesskey())));
}
// echo "<div style='background:red'>hhhhhhhhhhhhhhhi</div>";

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$cmid = optional_param('cmid', null, PARAM_INT);

$attemptobj = quiz_create_attempt_handling_errors($attemptid, $cmid);
$page = $attemptobj->force_page_number_into_range($page);
$PAGE->set_url($attemptobj->attempt_url(null, $page));

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/quiz:viewreports')) {
        redirect($attemptobj->review_url(null, $page));
    } else {
        throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'notyourattempt');
    }
}

// Check capabilities and block settings.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/quiz:attempt');
    if (empty($attemptobj->get_quiz()->showblocks)) {
        $PAGE->blocks->show_only_fake_blocks();
    }

} else {
    navigation_node::override_active_url($attemptobj->start_attempt_url());
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url(null, $page));
} else if ($attemptobj->get_state() == quiz_attempt::OVERDUE) {
    redirect($attemptobj->summary_url());
}

// Check the access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$accessmanager->setup_attempt_page($PAGE);
$output = $PAGE->get_renderer('mod_quiz');
$messages = $accessmanager->prevent_access();
if (!$attemptobj->is_preview_user() && $messages) {
    print_error('attempterror', 'quiz', $attemptobj->view_url(),
            $output->access_messages($messages));
}
if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
    redirect($attemptobj->start_attempt_url(null, $page));
}

// Set up auto-save if required.
$autosaveperiod = get_config('quiz', 'autosaveperiod');
if ($autosaveperiod) {
    $PAGE->requires->yui_module('moodle-mod_quiz-autosave',
            'M.mod_quiz.autosave.init', array($autosaveperiod));
}

// Log this page view.
$attemptobj->fire_attempt_viewed_event();

// Get the list of questions needed by this page.
$slots = $attemptobj->get_slots($page);

// Check.
if (empty($slots)) {
    throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noquestionsfound');
}

// Update attempt page, redirecting the user if $page is not valid.
if (!$attemptobj->set_currentpage($page)) {
    redirect($attemptobj->start_attempt_url(null, $attemptobj->get_currentpage()));
}

// Initialise the JavaScript.
$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->requires->js_init_call('M.mod_quiz.init_attempt_form', null, false, quiz_get_js_module());

// Arrange for the navigation to be displayed in the first region on the page.
$navbc = $attemptobj->get_navigation_panel($output, 'quiz_attempt_nav_panel', $page);
$regions = $PAGE->blocks->get_regions();
$PAGE->blocks->add_fake_block($navbc, reset($regions));

$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->set_title($attemptobj->attempt_page_title($page));
$PAGE->set_heading($attemptobj->get_course()->fullname);

if ($attemptobj->is_last_page($page)) {
    $nextpage = -1;
} else {
    $nextpage = $page + 1;
}

echo $output->attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage);
?>
<!-- <div class="modal fade bd-example-modal-sm" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content"> 
      <div class="modal-body">
       <b class="instruction"><i class="fa fa-info-circle" aria-hidden="true"></i> Instructions</b>
       <hr>
       <b class="instruction"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Report error in questions</b>
      </div>
    </div>
  </div>
</div> -->
<script type="text/javascript" src="./attempt.js"></script>
<link rel="stylesheet" type="text/css" href="attempt.css">
<script type="text/javascript">
    setTimeout(function(){
$('.Less20').css('display','none');
$("#left-arrow").click(function(){
    $('.Less10').css('display','block');
    $('.Less20').css('display','none');
});
},80);
   

var numItems = $('.qnbutton').length
var bn_pathname = window.location.href;
// var en_pathname = bn_pathname.replace("cmid=2058", "cmid=2058&page=10"); 
var ed_page_number = bn_pathname.substring(bn_pathname.lastIndexOf('/') + 40);
var st_page_number = bn_pathname.substring(bn_pathname.lastIndexOf('/') + 25);
var i=9;
// console.log(numItems);
// console.log(ed_page_number);
// console.log(st_page_number);
if (i<=numItems)
 {
    i+=9;
   // console.log(i);
 }
 else if(i<=numItems)
 {
    //console.log(i);
 }
$("#right-arrow").click(function(e){
  // $(location).attr('href');
     
    // window.location.replace(en_pathname);
    $('.Less10').css('display','none');
    $('.Less20').css('display','block');
});




</script>
<style type="text/css">
div#quiz-timer {
    display: none;
}
</style>