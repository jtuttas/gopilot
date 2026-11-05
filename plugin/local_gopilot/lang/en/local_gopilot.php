<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for local_gopilot.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['appurl'] = 'App URL';
$string['appurl_desc'] = 'Address of the GoPilot app (e.g. https://gopilot.example.org). Used for links in notifications. Leave empty to omit links.';
$string['autologinhttps'] = 'Automatic login into Moodle requires HTTPS.';
$string['autologinnotforadmins'] = 'For security reasons, administrators cannot be logged in automatically. Please log in to Moodle directly.';
$string['colour_green'] = 'green';
$string['colour_none'] = 'no signal';
$string['colour_red'] = 'red';
$string['colour_yellow'] = 'yellow';
$string['event_feedback_added'] = 'Coaching feedback added';
$string['event_plan_saved'] = 'Weekly plan saved';
$string['event_reflection_saved'] = 'Course reflection saved';
$string['gopilot:coach'] = 'Coach learners in GoPilot';
$string['gopilot:configure'] = 'Configure GoPilot for a course';
$string['gopilot:use'] = 'Use GoPilot as a learner';
$string['invalidmoodleurl'] = 'The link does not point to this Moodle site.';
$string['invalidthresholds'] = 'Invalid thresholds: the yellow threshold must be below the green or red threshold.';
$string['invalidweek'] = 'Invalid week "{$a}": expected the Monday as YYYY-MM-DD.';
$string['messageprovider:ampelchange'] = 'Your GoPilot Ampel changed to yellow or red';
$string['messageprovider:feedback'] = 'New coaching feedback in GoPilot';
$string['messageprovider:reflection'] = 'A learner saved a course reflection in GoPilot';
$string['messageprovider:reflectiondue'] = 'Reminder to reflect on your week in GoPilot';
$string['notalearner'] = 'The person is not a learner in this course.';
$string['notify_ampelchange_body'] = 'Your Ampel in {$a->course} is now {$a->colour}: {$a->reasons}. This is a hint, not a grade.';
$string['notify_ampelchange_subject'] = 'GoPilot: Ampel in {$a->course} is {$a->colour}';
$string['notify_feedback_body'] = '{$a->author} on week {$a->kw} in {$a->course}: {$a->message}';
$string['notify_feedback_subject'] = 'New feedback from {$a->author} ({$a->course})';
$string['notify_reflection_body'] = '{$a->learner} reflected on week {$a->kw} in {$a->course}.';
$string['notify_reflection_subject'] = 'New reflection by {$a->learner} ({$a->course})';
$string['notify_reflectiondue_body'] = 'Please reflect on week {$a->kw} for: {$a->courses}.';
$string['notify_reflectiondue_subject'] = 'GoPilot: time for your weekly reflection';
$string['pluginname'] = 'GoPilot';
$string['privacy:ampel'] = 'Ampel';
$string['privacy:feedbackgiven'] = 'Feedback given';
$string['privacy:metadata:core_message'] = 'GoPilot sends notifications through the Moodle messaging system.';
$string['privacy:metadata:core_userkey'] = 'GoPilot creates short-lived one-time keys to log users into Moodle from the app.';
$string['privacy:metadata:local_gopilot_ampel'] = 'Last computed support traffic light (Ampel) per learner and course.';
$string['privacy:metadata:local_gopilot_ampel:colour'] = 'Overall colour.';
$string['privacy:metadata:local_gopilot_ampel:courseid'] = 'Course.';
$string['privacy:metadata:local_gopilot_ampel:signals'] = 'Signals with values and reasons.';
$string['privacy:metadata:local_gopilot_ampel:timecomputed'] = 'Time of computation.';
$string['privacy:metadata:local_gopilot_ampel:userid'] = 'Learner.';
$string['privacy:metadata:local_gopilot_coursereflection'] = 'Course reflections of learners.';
$string['privacy:metadata:local_gopilot_coursereflection:courseid'] = 'Course.';
$string['privacy:metadata:local_gopilot_coursereflection:text'] = 'Reflection text.';
$string['privacy:metadata:local_gopilot_coursereflection:timemodified'] = 'Time modified.';
$string['privacy:metadata:local_gopilot_coursereflection:weekid'] = 'Planning week.';
$string['privacy:metadata:local_gopilot_feedback'] = 'Coaching feedback threads.';
$string['privacy:metadata:local_gopilot_feedback:authorid'] = 'Author of the entry.';
$string['privacy:metadata:local_gopilot_feedback:courseid'] = 'Course.';
$string['privacy:metadata:local_gopilot_feedback:message'] = 'Message.';
$string['privacy:metadata:local_gopilot_feedback:timecreated'] = 'Time created.';
$string['privacy:metadata:local_gopilot_feedback:weekid'] = 'Planning week of the learner.';
$string['privacy:metadata:local_gopilot_planitem'] = 'Learning steps planned in a week.';
$string['privacy:metadata:local_gopilot_planitem:cmid'] = 'Planned activity.';
$string['privacy:metadata:local_gopilot_planitem:completedatclose'] = 'Completion state at the end of the week.';
$string['privacy:metadata:local_gopilot_planitem:courseid'] = 'Course.';
$string['privacy:metadata:local_gopilot_planitem:weekid'] = 'Planning week.';
$string['privacy:metadata:local_gopilot_pushsub'] = 'Web Push subscriptions of devices.';
$string['privacy:metadata:local_gopilot_pushsub:auth'] = 'Authentication secret of the subscription.';
$string['privacy:metadata:local_gopilot_pushsub:endpoint'] = 'Push service address of the device.';
$string['privacy:metadata:local_gopilot_pushsub:p256dh'] = 'Public key of the subscription.';
$string['privacy:metadata:local_gopilot_pushsub:timecreated'] = 'Time created.';
$string['privacy:metadata:local_gopilot_pushsub:userid'] = 'User.';
$string['privacy:metadata:local_gopilot_selfassessment'] = 'Self-assessments per learning path.';
$string['privacy:metadata:local_gopilot_selfassessment:courseid'] = 'Course.';
$string['privacy:metadata:local_gopilot_selfassessment:remark'] = 'Comment.';
$string['privacy:metadata:local_gopilot_selfassessment:sectionid'] = 'Learning path.';
$string['privacy:metadata:local_gopilot_selfassessment:selflevel'] = 'Self-assessment level.';
$string['privacy:metadata:local_gopilot_selfassessment:weekid'] = 'Planning week.';
$string['privacy:metadata:local_gopilot_week'] = 'Planning weeks of learners.';
$string['privacy:metadata:local_gopilot_week:generalreflection'] = 'General reflection.';
$string['privacy:metadata:local_gopilot_week:goal'] = 'Weekly goal.';
$string['privacy:metadata:local_gopilot_week:reflectedat'] = 'Time of the last reflection.';
$string['privacy:metadata:local_gopilot_week:timemodified'] = 'Time modified.';
$string['privacy:metadata:local_gopilot_week:userid'] = 'Learner.';
$string['privacy:metadata:local_gopilot_week:week'] = 'Monday of the week.';
$string['privacy:metadata:webpush'] = 'Notifications are sent encrypted to the push service of the browser (e.g. Google, Mozilla, Apple).';
$string['privacy:metadata:webpush:body'] = 'Text of the notification.';
$string['privacy:metadata:webpush:title'] = 'Title of the notification.';
$string['privacy:metadata:webpush:url'] = 'Path in the app.';
$string['privacy:pushsubscriptions'] = 'Push subscriptions';
$string['pushheading'] = 'Web Push';
$string['pushheading_desc'] = 'VAPID keys for push notifications to the app. Generate them with <code>php local/gopilot/cli/generate_vapid.php</code>.';
$string['reflectionclosed'] = 'This week can no longer be reflected on. Reflection is possible for the current and the previous week.';
$string['selflevel1'] = 'confident';
$string['selflevel2'] = 'okay';
$string['selflevel3'] = 'unsure';
$string['selflevel4'] = 'lost';
$string['signal_backlog_nocompletion'] = 'Completion tracking is disabled in this course';
$string['signal_backlog_alldone'] = 'All {$a} due learning steps completed';
$string['signal_backlog_none'] = 'No expected completion date has passed yet';
$string['signal_backlog_onedone'] = 'The due learning step is completed';
$string['signal_backlog_oneopen'] = 'The due learning step is still open';
$string['signal_backlog_reason'] = '{$a->open} of {$a->due} due learning steps still open';
$string['signal_plan_nothingplanned'] = 'No learning step of this course planned';
$string['signal_plan_reason'] = '{$a->done} of {$a->total} planned learning steps completed ({$a->percent} %)';
$string['signal_reflection_noplannedweek'] = 'No closed week with planned steps yet';
$string['signal_reflection_one'] = 'Course reflection of the last planned week is missing';
$string['signal_reflection_present'] = 'Course reflection of the last planned week is present';
$string['signal_reflection_reason'] = 'Course reflection missing in {$a} planned weeks in a row';
$string['signal_selfassessment_none'] = 'No self-assessment yet';
$string['signal_selfassessment_reason'] = 'Self-assessment "{$a->level}" in {$a->path} (week {$a->kw})';
$string['task_freeze_weeks'] = 'Freeze completion state of closed GoPilot weeks';
$string['task_recalculate_ampel'] = 'Recalculate GoPilot Ampels';
$string['task_reflection_due'] = 'Remind learners to reflect (GoPilot)';
$string['vapidprivatekey'] = 'VAPID private key';
$string['vapidprivatekey_desc'] = 'Private key (Base64-URL, 32 bytes). Keep it secret.';
$string['vapidpublickey'] = 'VAPID public key';
$string['vapidpublickey_desc'] = 'Public key (Base64-URL, uncompressed P-256 point). The app uses it to subscribe.';
$string['vapidsubject'] = 'VAPID subject';
$string['vapidsubject_desc'] = 'Contact for push services, mailto: or https: URL. Empty = site URL (https) or no-reply address.';
$string['weekclosed'] = 'This week is closed and can no longer be planned.';
