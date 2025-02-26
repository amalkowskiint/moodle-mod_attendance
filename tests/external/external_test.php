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
 * External functions test for attendance plugin.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_attendance\external;

use externallib_advanced_testcase;
use mod_attendance_structure;
use stdClass;
use attendance_handler;
use external_api;
use mod_attendance_external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/attendance/classes/attendance_webservices_handler.php');
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

/**
 * This class contains the test cases for webservices.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_attendance
 * @runTestsInSeparateProcesses
 */
final class external_test extends externallib_advanced_testcase {
    /** @var core_course_category */
    protected $category;
    /** @var stdClass */
    protected $course;
    /** @var stdClass */
    protected $attendance;
    /** @var stdClass */
    protected $teacher;
    /** @var array */
    protected $students;
    /** @var array */
    protected $sessions;

    /**
     * Setup class.
     */
    public function setUp(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/attendance/externallib.php');
        $this->category = $this->getDataGenerator()->create_category();
        $this->course = $this->getDataGenerator()->create_course(['category' => $this->category->id]);
        $att = $this->getDataGenerator()->create_module('attendance', ['course' => $this->course->id]);
        $cm = $DB->get_record('course_modules', ['id' => $att->cmid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $this->attendance = new mod_attendance_structure($att, $cm, $course);

        $this->create_and_enrol_users();

        $this->setUser($this->teacher);

        $session = new stdClass();
        $session->sessdate = time();
        $session->duration = 6000;
        $session->description = "";
        $session->descriptionformat = 1;
        $session->descriptionitemid = 0;
        $session->timemodified = time();
        $session->statusset = 0;
        $session->groupid = 0;
        $session->absenteereport = 1;
        $session->calendarevent = 0;

        // Creating session.
        $this->sessions[] = $session;

        $this->attendance->add_sessions($this->sessions);
    }

    /**
     * Creating 10 students and 1 teacher.
     * @return void
     */
    protected function create_and_enrol_users() {
        $this->students = [];
        for ($i = 0; $i < 10; $i++) {
            $this->students[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        }

        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Test attendance_handler::get_courses_with_today_sessions.
     *
     * @covers \mod_attendance\external::get_courses_with_today_sessions
     * @return void
     * @throws \invalid_response_exception
     */
    public function test_get_courses_with_today_sessions(): void {
        $this->resetAfterTest(true);

        // Just adding the same session again to check if the method returns the right amount of instances.
        $this->attendance->add_sessions($this->sessions);

        $courseswithsessions = attendance_handler::get_courses_with_today_sessions($this->teacher->id);
        $courseswithsessions = external_api::clean_returnvalue(mod_attendance_external::get_courses_with_today_sessions_returns(),
            $courseswithsessions);

        $this->assertTrue(is_array($courseswithsessions));
        $this->assertEquals(count($courseswithsessions), 1);
        $course = array_pop($courseswithsessions);
        $this->assertEquals($course['fullname'], $this->course->fullname);
        $attendanceinstance = array_pop($course['attendance_instances']);
        $this->assertEquals(count($attendanceinstance['today_sessions']), 2);
    }

    /**
     * Test attendance_handler::get_courses_with_today_sessions multiple.
     *
     * @covers \mod_attendance\external::get_session
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_response_exception
     */
    public function test_get_courses_with_today_sessions_multiple_instances(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Make another attendance.
        $att = $this->getDataGenerator()->create_module('attendance', ['course' => $this->course->id]);
        $cm = $DB->get_record('course_modules', ['id' => $att->cmid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $second = new mod_attendance_structure($att, $cm, $course);

        // Just add the same session.
        $secondsession = clone $this->sessions[0];

        $second->add_sessions([$secondsession]);

        $courseswithsessions = attendance_handler::get_courses_with_today_sessions($this->teacher->id);
        $courseswithsessions = external_api::clean_returnvalue(mod_attendance_external::get_courses_with_today_sessions_returns(),
            $courseswithsessions);

        $this->assertTrue(is_array($courseswithsessions));
        $this->assertEquals(count($courseswithsessions), 1);
        $course = array_pop($courseswithsessions);
        $this->assertEquals(count($course['attendance_instances']), 2);
    }

    /**
     * Test attendance_handler::get_session.
     *
     * @covers \mod_attendance\external::get_session
     * @return void
     * @throws \invalid_response_exception
     */
    public function test_get_session(): void {
        $this->resetAfterTest(true);

        $courseswithsessions = attendance_handler::get_courses_with_today_sessions($this->teacher->id);
        $courseswithsessions = external_api::clean_returnvalue(mod_attendance_external::get_courses_with_today_sessions_returns(),
            $courseswithsessions);

        $course = array_pop($courseswithsessions);
        $attendanceinstance = array_pop($course['attendance_instances']);
        $session = array_pop($attendanceinstance['today_sessions']);

        $sessioninfo = attendance_handler::get_session($session['id']);
        $sessioninfo = external_api::clean_returnvalue(mod_attendance_external::get_session_returns(),
            $sessioninfo);

        $this->assertEquals($this->attendance->id, $sessioninfo['attendanceid']);
        $this->assertEquals($session['id'], $sessioninfo['id']);
        $this->assertEquals(count($sessioninfo['users']), 10);
    }

    /**
     * Test get session with group.
     *
     * @covers \mod_attendance\external::get_courses_with_today_sessions
     * @return void
     * @throws \coding_exception
     * @throws \invalid_response_exception
     */
    public function test_get_session_with_group(): void {
        $this->resetAfterTest(true);

        // Create a group in our course, and add some students to it.
        $group = new stdClass();
        $group->courseid = $this->course->id;
        $group = $this->getDataGenerator()->create_group($group);

        for ($i = 0; $i < 5; $i++) {
            $member = new stdClass;
            $member->groupid = $group->id;
            $member->userid = $this->students[$i]->id;
            $this->getDataGenerator()->create_group_member($member);
        }

        // Add a session that's identical to the first, but with a group.
        $midnight = usergetmidnight(time()); // Check if this test is running during midnight.
        $session = clone $this->sessions[0];
        $session->groupid = $group->id;
        $session->sessdate += 1; // Make sure it appears second in the list.
        $this->attendance->add_sessions([$session]);

        $courseswithsessions = attendance_handler::get_courses_with_today_sessions($this->teacher->id);

        // This test is fragile when running over midnight - check that it is still the same day, if not, run this again.
        // This isn't really ideal code, but will hopefully still give a valid test.
        if (empty($courseswithsessions) && $midnight !== usergetmidnight(time())) {
            $this->attendance->add_sessions([$session]);
            $courseswithsessions = attendance_handler::get_courses_with_today_sessions($this->teacher->id);
        }
        $courseswithsessions = external_api::clean_returnvalue(mod_attendance_external::get_courses_with_today_sessions_returns(),
            $courseswithsessions);

        $course = array_pop($courseswithsessions);
        $attendanceinstance = array_pop($course['attendance_instances']);
        $session = array_pop($attendanceinstance['today_sessions']);

        $sessioninfo = attendance_handler::get_session($session['id']);
        $sessioninfo = external_api::clean_returnvalue(mod_attendance_external::get_session_returns(),
            $sessioninfo);

        $this->assertEquals($session['id'], $sessioninfo['id']);
        $this->assertEquals($group->id, $sessioninfo['groupid']);
        $this->assertEquals(count($sessioninfo['users']), 5);
    }

    /**
     *  Test update user status.
     *
     * @covers \mod_attendance\external::update_user_status
     * @return void
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     */
    public function test_update_user_status(): void {
        $this->resetAfterTest(true);

        $courseswithsessions = attendance_handler::get_courses_with_today_sessions($this->teacher->id);
        $courseswithsessions = external_api::clean_returnvalue(mod_attendance_external::get_courses_with_today_sessions_returns(),
            $courseswithsessions);

        $course = array_pop($courseswithsessions);
        $attendanceinstance = array_pop($course['attendance_instances']);
        $session = array_pop($attendanceinstance['today_sessions']);

        $sessioninfo = attendance_handler::get_session($session['id']);
        $sessioninfo = external_api::clean_returnvalue(mod_attendance_external::get_session_returns(),
            $sessioninfo);

        $student = array_pop($sessioninfo['users']);
        $status = array_pop($sessioninfo['statuses']);
        $statusset = $sessioninfo['statusset'];

        $result = mod_attendance_external::update_user_status($session['id'], $student['id'], $this->teacher->id,
            $status['id'], $statusset);
        $result = external_api::clean_returnvalue(mod_attendance_external::update_user_status_returns(), $result);

        $sessioninfo = attendance_handler::get_session($session['id']);
        $sessioninfo = external_api::clean_returnvalue(mod_attendance_external::get_session_returns(),
            $sessioninfo);

        $log = array_pop($sessioninfo['attendance_log']);
        $this->assertEquals($student['id'], $log['studentid']);
        $this->assertEquals($status['id'], $log['statusid']);
    }

    /**
     * Test adding new attendance record via ws.
     *
     * @covers \mod_attendance\external::add_attendance
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     */
    public function test_add_attendance(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();

        // Become a teacher.
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->setUser($teacher);

        // Check attendance does not exist.
        $this->assertCount(0, $DB->get_records('attendance', ['course' => $course->id]));

        // Create attendance.
        $result = mod_attendance_external::add_attendance($course->id, 'test', 'test', NOGROUPS);
        $result = external_api::clean_returnvalue(mod_attendance_external::add_attendance_returns(), $result);

        // Check attendance exist.
        $this->assertCount(1, $DB->get_records('attendance', ['course' => $course->id]));
        $record = $DB->get_record('attendance', ['id' => $result['attendanceid']]);
        $this->assertEquals($record->name, 'test');

        // Check group.
        $cm = get_coursemodule_from_instance('attendance', $result['attendanceid'], 0, false, MUST_EXIST);
        $groupmode = (int)groups_get_activity_groupmode($cm);
        $this->assertEquals($groupmode, NOGROUPS);

        // Create attendance with "separate groups" group mode.
        $result = mod_attendance_external::add_attendance($course->id, 'testsepgrp', 'testsepgrp', SEPARATEGROUPS);
        $result = external_api::clean_returnvalue(mod_attendance_external::add_attendance_returns(), $result);

        // Check attendance exist.
        $this->assertCount(2, $DB->get_records('attendance', ['course' => $course->id]));
        $record = $DB->get_record('attendance', ['id' => $result['attendanceid']]);
        $this->assertEquals($record->name, 'testsepgrp');

        // Check group.
        $cm = get_coursemodule_from_instance('attendance', $result['attendanceid'], 0, false, MUST_EXIST);
        $groupmode = (int)groups_get_activity_groupmode($cm);
        $this->assertEquals($groupmode, SEPARATEGROUPS);

        // Create attendance with wrong group mode.
        $this->expectException('invalid_parameter_exception');
        $result = mod_attendance_external::add_attendance($course->id, 'test1', 'test1', 100);
    }

    /**
     * Test remove attendance va ws.
     *
     * @covers \mod_attendance\external::remove_attendance
     * @return void
     * @throws \dml_exception
     * @throws \invalid_response_exception
     */
    public function test_remove_attendance(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Become a teacher.
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, $teacherrole->id);
        $this->setUser($teacher);

        // Check attendance exists.
        $this->assertCount(1, $DB->get_records('attendance', ['course' => $this->course->id]));
        $this->assertCount(1, $DB->get_records('attendance_sessions', ['attendanceid' => $this->attendance->id]));

        // Remove attendance.
        $result = mod_attendance_external::remove_attendance($this->attendance->id);
        $result = external_api::clean_returnvalue(mod_attendance_external::remove_attendance_returns(), $result);

        // Check attendance removed.
        $this->assertCount(0, $DB->get_records('attendance', ['course' => $this->course->id]));
        $this->assertCount(0, $DB->get_records('attendance_sessions', ['attendanceid' => $this->attendance->id]));
    }

    /**
     * Test add session to existing attendnace via ws.
     *
     * @covers \mod_attendance\external::add_session
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     */
    public function test_add_session(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Become a teacher.
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->setUser($teacher);

        // Create attendance with separate groups mode.
        $attendancesepgroups = mod_attendance_external::add_attendance($course->id, 'sepgroups', 'test', SEPARATEGROUPS);
        $attendancesepgroups = external_api::clean_returnvalue(mod_attendance_external::add_attendance_returns(),
                                                               $attendancesepgroups);

        // Check attendance exist.
        $this->assertCount(1, $DB->get_records('attendance', ['course' => $course->id]));

        // Create session and validate record.
        $time = time();
        $duration = 3600;
        $result = mod_attendance_external::add_session($attendancesepgroups['attendanceid'],
            'testsession', $time, $duration, $group->id, true);
        $result = external_api::clean_returnvalue(mod_attendance_external::add_session_returns(), $result);

        $this->assertCount(1, $DB->get_records('attendance_sessions', ['id' => $result['sessionid']]));
        $record = $DB->get_record('attendance_sessions', ['id' => $result['sessionid']]);
        $this->assertEquals($record->description, 'testsession');
        $this->assertEquals($record->attendanceid, $attendancesepgroups['attendanceid']);
        $this->assertEquals($record->groupid, $group->id);
        $this->assertEquals($record->sessdate, $time);
        $this->assertEquals($record->duration, $duration);
        $this->assertEquals($record->calendarevent, 1);

        // Create session with no group in "separate groups" attendance.
        $this->expectException('invalid_parameter_exception');
        mod_attendance_external::add_session($attendancesepgroups['attendanceid'], 'test', time(), 3600, 0, false);
    }

    /**
     * Test add session group in no group - error.
     *
     * @covers \mod_attendance\external::add_session
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     */
    public function test_add_session_group_in_no_group_exception(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Become a teacher.
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->setUser($teacher);

        // Create attendance with no groups mode.
        $attendancenogroups = mod_attendance_external::add_attendance($course->id, 'nogroups',
                                                                 'test', NOGROUPS);
        $attendancenogroups = external_api::clean_returnvalue(mod_attendance_external::add_attendance_returns(),
            $attendancenogroups);

        // Check attendance exist.
        $this->assertCount(1, $DB->get_records('attendance', ['course' => $course->id]));

        // Create session with group in "no groups" attendance.
        $this->expectException('invalid_parameter_exception');
        mod_attendance_external::add_session($attendancenogroups['attendanceid'], 'test', time(), 3600, $group->id, false);
    }

    /**
     * Test add sesssion to invalid group.
     *
     * @covers \mod_attendance\external::add_session
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     */
    public function test_add_session_invalid_group_exception(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        // Become a teacher.
        $teacher = self::getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->setUser($teacher);

        // Create attendance with visible groups mode.
        $attendancevisgroups = mod_attendance_external::add_attendance($course->id, 'visgroups', 'test', VISIBLEGROUPS);
        $attendancevisgroups = external_api::clean_returnvalue(mod_attendance_external::add_attendance_returns(),
                                                               $attendancevisgroups);

        // Check attendance exist.
        $this->assertCount(1, $DB->get_records('attendance', ['course' => $course->id]));

        // Create session with invalid group in "visible groups" attendance.
        $this->expectException('invalid_parameter_exception');
        mod_attendance_external::add_session($attendancevisgroups['attendanceid'], 'test', time(), 3600, $group->id + 100, false);
    }

    /**
     * Test remove session via ws.
     *
     * @covers \mod_attendance\external::remove_session
     * @return void
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     */
    public function test_remove_session(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Create attendance with no groups mode.
        $attendance = mod_attendance_external::add_attendance($this->course->id, 'test', 'test', NOGROUPS);
        $attendance = external_api::clean_returnvalue(mod_attendance_external::add_attendance_returns(), $attendance);

        // Create sessions.
        $result0 = mod_attendance_external::add_session($attendance['attendanceid'], 'test0', time(), 3600, 0, false);
        $result0 = external_api::clean_returnvalue(mod_attendance_external::add_session_returns(), $result0);
        $result1 = mod_attendance_external::add_session($attendance['attendanceid'], 'test1', time(), 3600, 0, false);
        $result1 = external_api::clean_returnvalue(mod_attendance_external::add_session_returns(), $result1);

        $this->assertCount(2, $DB->get_records('attendance_sessions', ['attendanceid' => $attendance['attendanceid']]));

        // Delete session 0.
        $result = mod_attendance_external::remove_session($result0['sessionid']);
        $result = external_api::clean_returnvalue(mod_attendance_external::remove_session_returns(), $result);
        $this->assertCount(1, $DB->get_records('attendance_sessions', ['attendanceid' => $attendance['attendanceid']]));

        // Delete session 1.
        $result = mod_attendance_external::remove_session($result1['sessionid']);
        $result = external_api::clean_returnvalue(mod_attendance_external::remove_session_returns(), $result);
        $this->assertCount(0, $DB->get_records('attendance_sessions', ['attendanceid' => $attendance['attendanceid']]));
    }

    /**
     * Test session creates cal event.
     *
     * @covers \mod_attendance\external::add_attendance
     * @return void
     * @throws \invalid_parameter_exception
     * @throws \invalid_response_exception
     */
    public function test_add_session_creates_calendar_event(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Create attendance with no groups mode.
        $attendance = mod_attendance_external::add_attendance($this->course->id, 'test', 'test', NOGROUPS);
        $attendance = external_api::clean_returnvalue(mod_attendance_external::add_attendance_returns(), $attendance);

        // Prepare events tracing.
        $sink = $this->redirectEvents();

        // Create session with no calendar event.
        $result = mod_attendance_external::add_session($attendance['attendanceid'], 'test0', time(), 3600, 0, false);
        $result = external_api::clean_returnvalue(mod_attendance_external::add_session_returns(), $result);

        // Capture the event.
        $events = $sink->get_events();
        $sink->clear();

        // Validate.
        $this->assertCount(1, $events);
        $this->assertInstanceOf('\mod_attendance\event\session_added', $events[0]);

        // Create session with calendar event.
        $result = mod_attendance_external::add_session($attendance['attendanceid'], 'test0', time(), 3600, 0, true);
        $result = external_api::clean_returnvalue(mod_attendance_external::add_session_returns(), $result);

        // Capture the event.
        $events = $sink->get_events();
        $sink->clear();

        // Validate the event.
        $this->assertCount(2, $events);
        $this->assertInstanceOf('\core\event\calendar_event_created', $events[0]);
        $this->assertInstanceOf('\mod_attendance\event\session_added', $events[1]);
    }

    /**
     * Test get sessions.
     *
     * @covers \mod_attendance\external::get_sessions
     * @return void
     * @throws \invalid_response_exception
     */
    public function test_get_sessions(): void {
        $this->resetAfterTest(true);

        $courseswithsessions = attendance_handler::get_courses_with_today_sessions($this->teacher->id);
        $courseswithsessions = external_api::clean_returnvalue(mod_attendance_external::get_courses_with_today_sessions_returns(),
            $courseswithsessions);

        foreach ($courseswithsessions as $course) {

            $attendanceinstances = $course['attendance_instances'];

            foreach ($attendanceinstances as $attendanceinstance) {

                $sessionsinfo = $attendanceinstance['today_sessions'];

                foreach ($sessionsinfo as $sessioninfo) {

                    $sessions = attendance_handler::get_sessions($sessioninfo['attendanceid']);
                    $sessions = external_api::clean_returnvalue(mod_attendance_external::get_sessions_returns(),
                        $sessions);

                    foreach ($sessions as $session) {
                        $sessiontocompareagainst = attendance_handler::get_session($session['id']);
                        $sessiontocompareagainst = external_api::clean_returnvalue(mod_attendance_external::get_session_returns(),
                            $sessiontocompareagainst);

                        $this->assertEquals($this->attendance->id, $session['attendanceid']);
                        $this->assertEquals($sessiontocompareagainst['id'], $session['id']);
                        $this->assertEquals(count($session['users']), count($sessiontocompareagainst['users']));
                    }
                }
            }
        }
    }
}
