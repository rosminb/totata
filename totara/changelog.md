Release 16.6 (31st October 2022):
=================================

Performance improvements
------------------------

  TL-34361 Improved the performance when a user signs up for a seminar event

    When a user books for a seminar event, the system verifies whether this will
    result in a booking conflict. Previously, this check was done multiple times
    during the signup process. This patch now caches the result for one minute,
    allowing the reduction of repeating the same query multiple times during this
    time.

  TL-35721 Improved performance of Manager's manager relationship with MariaDB

Improvements
------------

  TL-35783 Fixed the duplicate triggering of 'Program future assigned' event

    When a future assignment is created for a user in a program, the 'Program future
    assigned' event will only be triggered once per future assignment.

Bug fixes
---------

  TL-32527 Hid general section in course resolver if it is empty

    Fixed an issue where a course's 'General' section would appear in the Totara
    mobile app, even if there were no activities in that section.

  TL-34335 Fixed mobile navigation height
  TL-34787 Workaround to support Redis Cluster
  TL-34978 Border when placing a drag and drop text answer no longer disappears after placing the answer
  TL-34994 Cherry-picked MDL-63959 to fix an issue with nested dependencies in the feedback module
  TL-35043 Created new 'Content provider' string and deprecated old string

    The previous string key was 'course_provider', the new string key is
    'content_provider'.

  TL-35073 Improved the context checks for the activity completion notification
  TL-35087 Fixed HTML rendering when disableconsistentcleaning is enabled
  TL-35105 Fixed .mov files not rendering for Chrome/Edge in Engage
  TL-35166 Fixed language debug parameter not being passed to GraphQL queries

    When '&strings=1' is added to a Totara URL, the translatable string names are
    displayed in the page, rather than the strings themselves. This was not working
    correctly when translated strings were returned by GraphQL queries, and has been
    fixed.

  TL-35188 Fixed historic course completion rule not including users who have more than one historic record
  TL-35220 Fixed iCal attachment files order when sending seminar notifications with multiple sessions
  TL-35293 Set mapping for the question category while restoring again
  TL-35431 Improved keyboard functionality of the Tui taglist component
  TL-35518 Added validation on resolver class name and schedule in notification preference GraphQL mutation
  TL-35533 Added validation around extended context when toggling notifiable events
  TL-35561 Fixed query validation for retrieving notification preferences
  TL-35652 Fixed a bug where selecting no courses under a course completion criteria group "Add courses" modal would instead cause all the courses to be added
  TL-35714 Override check_length_limit() for source_user_csv to prevent dataloss
  TL-35782 Fixed incorrect debug message when $CFG->dataroot/lang is a symbolic link

    When $CFG->langotherroot is specified and the /lang directory in $CFG->dataroot
    is a symbolic link to the same folder, the built-in language installer no longer
    shows a debug warning about it not supporting alternative locations.

  TL-35787 Updated notification scheduled tasks to include the original error message when a notification fails to send
  TL-35839 Fixed notification queue task failing for seminar booking confirmation under certain conditions

    The seminar notification placeholder code used a method that wasn't loaded under
    certain conditions, including having an internal 'plain' body format set for the
    booking confirmation notification.

Tui front end framework
-----------------------

  TL-35525 Updated focus style of dropdown to meet accessibility requirements

Contributions
-------------

  * Alex Damsted at Kineo Pacific - TL-35188
  * Jo Jones at Kineo UK - TL-35783



Release 16.5 (28th September 2022):
===================================

Security issues
---------------

  TL-35414 Fixed remote code execution risk when restoring a malformed backup file with HTML block configuration (MSA-22-0024 / CVE-2022-40314) 

Performance improvements
------------------------

  TL-35218 Improved the performance of the current learning block and the GraphQL query returning the current learning items for Mobile

Bug fixes
---------

  TL-34147 Atto editor no longer autoplays videos and audio media while editing
  TL-34706 Updated the Atto editor to only auto-size to match the textarea(s) it replaces if the editor is visible
  TL-34753 Fixed time enrolled for course completions not being recorded in some cases

    Before, users enrolments were being marked as enrolled differently if the old
    and deprecated completionstartonenrol setting was enabled. This has now been
    fixed.

  TL-34870 Fixed Trainer column for Feedback Summary report builder not being populated if trainer is enrolled
  TL-34993 Updated the select options in the "Completion of other course sections" section on the course completion settings page to have a title attribute and to overflow with an ellipsis
  TL-35012 Fixed enrolment queries not using greater than or equal when comparing to timestart

    Previously, all database queries getting the current enrolments for users have
    not considered the timestart value as being included. All comparisons have been
    set to timestart < :now (where now is the current timestamp). Technically this
    is incorrect as the timestart value needs to be included. However, in reality it
    is quite unlikely that this would have an impact on existing sites. This has now
    been fixed.

  TL-35022 Fixed an error on tabs when sharing resources with a workspace when user does not have the right capabilities
  TL-35144 Fixed column type normalisation function in MySQL

    We have applied a multibyte-aware string function instead when checking for
    column types in MySQL, as an issue was found while using Turkish language.

  TL-35200 Fixed keyboard navigation to the close button on a modal
  TL-35208 Ensured the correct return type for totara_message_eventdata() function is used
  TL-35251 Fixed duplicate HTML ids on custom field icons
  TL-35253 Fixed a regression from TL-34907 to ensure completion progress is displayed correctly in the Current Learning block
  TL-35276 Fixed the display of selected items when using multiselect questions in the Feedback module
  TL-35292 Fixed relative due date fields in the legacy program assignment interface to allow a setting of '0'
  TL-35390 Fixed issue where Weka toolbar was not navigable by keyboard
  TL-35392 Fixed failing unit tests due to a change in the PHPUnit upstream library

Contributions
-------------

  * Brad Simpson - Kineo USA - TL-35251



Release 16.4 (26th August 2022):
================================

Important
---------

  TL-35164 Fixed upgrade of relative due date calculation of program/certification assignments potentially resulting in loss of data 

    This fixes a potential data loss issue.

    It affects sites which got upgraded from any version of 14.5 or higher to 15 or
    16 (not including this fix) and which make use of relative due dates in program
    or certification assignments. 

    Sites upgraded from a version prior to 14.5 directly to 15 or 16 are not
    affected.

    This issue had an immediate impact on relative due date periods set on
    program/certification assignment. This did not affect existing program
    completion due dates but any future due date calculations used 0 DAYS as a time
    frame potentially resulting in learners not being able to complete their
    program.

    In Totara 14.5 we changed the way relative due dates are calculated. Previously,
    it used a static 30 days per month during the calculation of the real due date.
    To improve accuracy we changed this to use the correct date functionality and
    therefore count in the real number of days for the timeframe. 

    To convert the existing data to the new data we introduced an upgrade step which
    migrates the records into the new format using new columns in the
    prog_assignment database table. Due to a bug in the upgrade code, assignments
    already using the new structure for relative due dates are migrated wrongly
    during the next upgrade, which sets the affected relative periods to 0 DAYS. 

    The upgrade step has now been fixed to ensure the program/certification
    assignment data for relative due dates is correctly migrated, even when run
    multiple times.

    For sites who have already upgraded the following SQL query can be used to
    determine if your site was affected.
    If a result is returned then please seek support from us and we will assist you
    in confirming the issue, and in supporting you to recover the lost data.

    SELECT
        p.id AS program_id,
        p.idnumber as idnumber,
        p.fullname AS name,
        CASE WHEN p.certifid IS NOT NULL THEN 'certification'
            ELSE 'program' END AS program_or_certification,
        CASE WHEN pa.assignmenttype = 1 THEN 'organisation'
            WHEN pa.assignmenttype = 2 THEN 'position'
            WHEN pa.assignmenttype = 3 THEN 'audience'
            WHEN pa.assignmenttype = 5 THEN 'individual'
            WHEN pa.assignmenttype = 6 THEN 'job assignment'
            WHEN pa.assignmenttype = 7 THEN 'learning plan'
            ELSE ''
        END AS assignment_type
    FROM mdl_prog_assignment AS pa
    LEFT JOIN mdl_prog AS p ON p.id = pa.programid
    WHERE pa.completionoffsetamount = 0
        AND pa.completiontime IS NULL
        AND pa.completionoffsetunit = 2;

Improvements
------------

  TL-30485 Updated strings on the Engage access form when creating a resource and added an info icon button to the topic selector
  TL-34166 Improved wording in content visibility settings of resources and playlists
  TL-34864 Improved UI behaviour for Tenant default values field when the "Override with file and defaults" value for existing user details field is selected

    When "Override with file and defaults" value is selected then "Tenant default
    values" field will be disabled.

  TL-34888 Allow escaping hyphens with a backslash in OAuth2 field mapping

    The hyphen character is used as an object nesting divider i.e.
    'Country-region-city' field will look up $country->region->city in the userinfo
    data source. This prevents using the hyphen character as a regular character. We
    added an ability to use a backslash character before the hyphen to treat it as a
    regular hyphen.

  TL-35052 Increased size of the Totara Menu URL field to allow for a url up to 1333 characters in length

Bug fixes
---------

  TL-32601 Changed 'Course Provider' language string to 'Content provider'
  TL-32996 Fixed Auth plugins settings not being updated when custom fields are updated
  TL-33236 Made settings_navigation_tree GraphQL query and its tests more robust

    Fixed an issue where the query would have returned a site admin node if called
    with an admin page and the setting 'legacyadminsettingsmenu' being disabled.

  TL-33565 Fixed intermittent failing unit test caused by a timing issue
  TL-33607 Improved XMLDB Editor path validation for included files
  TL-34200 Replaced the icon used for edit personal goals link with the editstring icon
  TL-34447 Fixed an issue where a course creator was unable to select an activity type when creating single-activity course

    This patch creates a new capability, 'format/singleactivity:addanyactivity',
    that allows the bearer to select any type of activity when creating a
    single-activity course. On upgrade, this capability is given to all roles based
    on the course creator archetype.

  TL-34551 Suppressed notifications when updating temporary manager via HR import
  TL-34652 Fixed the displaying of the suspended user option in user sources page
  TL-34683 Fixed course category caching issue with icons display when searching for courses in the course administration.
  TL-34776 Fixed the LTI tool check to display a green mark check when a valid URL is entered
  TL-34785 Added has_middleware interface to server/totara/reportbuilder/classes/webapi/resolver/query/template.php resolver
  TL-34808 Fixed stderr not being redirected to stdout when using pcntl extension on PHP version 8.0.20 or 8.1.7

    On PHP 8.0.20 and 8.1.7 a patch has been released which breaks widely used code
    to redirect stderr to stdout in CLI scripts. Even though 8.0.21 and 8.1.8
    reverted the change Totara code has been fixed to work on all supported
    versions.

  TL-34861 Improved error handling for invalid cache in appraisal multichoice questions
  TL-34865 Fixed a help lang string for bulk adding goals
  TL-34868 Fixed the machine learning healthcheck showing exceptions before the GraphQL cache had fully built
  TL-34889 Included library files which can not be auto loaded 
  TL-34890 Fixed the add audience dialogue overlapping the footer

    The add audiences dialogue  has been fixed so that the list of audiences no
    longer overlaps the footer.

  TL-34907 Added a course completion due date for Current Learning block and Record of Learning reportbuilder sources
  TL-34920 Fixed user custom fields not being populated when uploading tenant users
  TL-34929 removed role attribute in course header and summary section HTML
  TL-35025 Ensured empty Report Builder scheduled reports are not sent or saved
  TL-35084 Fixed visibility column exporting data as HTML in Visible Learning report source
  TL-35165 Fixed an exception when including child positions in a position/organisation assignment 

    The set_duedate() method is not compatible to the duedate parameter being null..
    This changes make sure to convert null to 0 before calling the set_duedate()
    method.

Technical changes
-----------------

  TL-34899 Fixed an issue where isset on an entity returns false if the relationship exists but is not loaded

Tui front end framework
-----------------------

  TL-29586 Added `toHaveNoViolations()` to jest's expect() result

    When using JavaScript unit tests (as provided by jest), a `toHaveNoViolations`
    function has been added to the `expect()` return value. This allows the test to
    check the accessibility of the given component and avoids importing the function
    from jest-axe package `toHaveNoViolations()`

  TL-34555 Fixed the function dom/position/getBox to be functional in IE11

Library updates
---------------

  TL-33146 Upgraded development library "stylelint" from 12.0 to 14.6
  TL-34192 Updated indirect npm dependencies to eliminate vulnerability warnings

    * Updated indirect dependencies with "npm audit fix"



Release 16.3 (28th July 2022):
==============================

Security issues
---------------

  TL-34908 Increased sanitisation for question upload in the lesson module 

    Previously users with the necessary capability to upload questions for the
    lesson module (teachers, managers, and admins by default), could potentially
    upload a malformed package resulting in an arbitrary file read risk.

  TL-34909 Fixed XSS and blind SSRF vulnerability in SCORM activities

    Insufficient sanitising of SCORM track details caused XSS and SSRF risks. This
    has now been fixed.

Improvements
------------

  TL-34296 Added client side "alphanumeric" validation and help text for custom field short names  to improve user experience
  TL-34570 Added Totara 17 to the Environment Checks page

    Added the new server requirements for Totara 17 to the Admin -> Server ->
    Environment Checks page. Totara 17 requires a minimum PHP version of 7.4.3.

  TL-34767 Files larger than 5gb can now be uploaded when using cloud file storage with AWS S3
  TL-34844 Cherry-picked MDL-46542 to allow restricting duration units menu to a subset of the available units

Bug fixes
---------

  TL-16199 Added a new capability to allow staff members to change a personal goal that was assigned by their manager

    This new capability 'managemanagerassignedgoal' is intended to be used in the
    user context.  It is recommended to apply this capability to the Authenticated
    user role, if you want to allow staff members to change personal goals that were
    assigned by their manager

  TL-33319 Fixed restoring a course backup on another installation with a different system context id
  TL-34268 Deprecated workaround_max_input_vars() function

    Because PHP 8.0 defaults to warning when max_input_vars is exceeded, we have
    deprecated the function used to rebuild input vars from stdin when they exceed
    the PHP limit. System administrators are recommended to set max_input_vars
    greater than 5000 in php.ini.

  TL-34367 Prevented ical attachments being sent in seminar notifications for requests that have not been approved
  TL-34391 Added validation for the MySQL database name during the installations without a config.php file
  TL-34461 Fixed the button alignment in the image modal of the Atto editor
  TL-34478 Fixed the current_coursets field in the mobile GraphQL program resolver

    Previously when the current courseset for a program fetched via the mobile
    graphql calls contained 2 sets joined by an "and" condition, if the second set
    was completed before the first set then subsequent fetches would return the
    current courseset as empty. Subsequent queries will now return the first set
    allowing further progress in the program.

  TL-34538 Ensured Program and Certification notification placeholders display correctly when editing a notification after upgrade from T13
  TL-34546 Fixed the incorrect notification type being displayed

    Notifications will now display as Factory (meaning that it is a built-in
    notification provided by Totara), Amended (meaning that some property has been
    overridden from the default) or Custom (meaning that it was manually created in
    this context) when in the system context. Or as Inherited, Amended or Custom in
    other contexts.

  TL-34548 Fixed hiding draft responses for 'hide incomplete responses' setting
  TL-34676 Fixed overridden titles in user profile blocks not being visible
  TL-34689 Fixed seminar sessions placeholder replacement when the opening tag is at the start of the message
  TL-34692 Fixed the use of an undefined subject_instance table alias
  TL-34740 Ensured the notification upgrade script from TL-34108 works properly for MySQL/MariaDB
  TL-34741 Fixed backup_nested_element for notifications (mod_facetoface)
  TL-34795 Increased the column length for course section titles to better support the multiple language filter
  TL-34806 Fixed custom field multi checkboxes alignment
  TL-34815 Fixed string to float conversion in the quiz module
  TL-34822 Removed container_perform and container_workspace from course backup searches
  TL-34837 Fixed an error when adding a date filter with the 'between dates' option disabled to a report
  TL-34869 Fixed the "member added" string in workspace notifications

Tui front end framework
-----------------------

  TL-34086 Updated webpack and other packages to support Node 18

    If you have previously customised webpack builds using the hooks
    in {{build.config.js}} or by modifying the core webpack configuration, you may
    have to update these to be compatible with webpack 5. If you have not made any
    customisations to the webpack builds, you shouldn't need to take any action
    here.

Library updates
---------------

  TL-34375 Updated the SVGGraph library to improve support for PHP 8.1
  TL-34383 Updated the SCSSSPHP library to improve support for PHP 8.1



Release 16.2 (28th June 2022):
==============================

Important
---------

  TL-33943 Fixed the "no indirect reports" rule

    Previously, the "no indirect reports" rule for dynamic audiences was incorrect -
    it targeted those users that had no immediate reports. When combined with a
    direct report of at least 1 rule, it resulted in an empty audience.

    This patch corrects the indirect report rule. However, it also means membership
    in existing audiences that make use of this rule could unexpectedly change,
    affecting course/program/certification enrolments  or perform activity
    participants for example.

Security issues
---------------

  TL-34739 Fixed remote code execution vulnerability in the 'Annotate PDF' assignment feedback plugin

    A learner exploiting this vulnerability could upload a carefully-crafted file as
    an assignment submission and run arbitrary shell commands on the server. 

    This only affects Totara instances with 'Annotate PDF' selected as the
    assignment feedback plugin in system settings and ghostscript < 9.50 installed
    on the server.

Performance improvements
------------------------

  TL-33272 Improved how regrading of courses is handled

    When a course has more than 100 enrolments or 100 grade items, any regrading
    necessary (such as adding a new activity or changing grade settings) will be
    done on the next cron run rather than blocking page load. When this happens, a
    message is displayed to the user to let them know that grades are being
    recalculated.

    For smaller courses, the re-grade is done in real time. 

    This is a follow up to an earlier patch (TL-31570) which introduced background
    regrading, but only when adding a new activity.

  TL-33363 Deleting an enrolment instance has been shifted to a background task

    Previously when deleting an enrolment instance from a course, users would be
    unenrolled immediately and then the instance would be deleted. If the number of
    enrolled users was large, the page may take a long time to respond. 

    With this patch, the deletion is shifted into a background task run on the next
    cron run.

  TL-34382 Improved performance for the user search when selecting performance activity participants
  TL-34400 Fixed GraphQL performance regression from latest graphql-php library update

    The latest version of the webonyx/graphql-php library added schema validation
    that is unnecessarily repeated for each call by default. This patch switches the
    unnecessary validation off, improving performance of all GraphQL operations.

Improvements
------------

  TL-29549 Added displaying manual rating comments in the competency activity log

    Comments that were added when manually rating a user's competency will now be
    displayed in the user's activity log of that competency.

  TL-32119 Added the missing event trigger for suspended users
  TL-33052 Added a seminar 'Attendance status' report builder column and filter
  TL-33491 Started recording any changed HR Import settings within the config log database table
  TL-33986 Added an asterisk to required fields in installation/upgrade
  TL-34228 Removed the separation of evidence shown in Record of Learning and the Evidence bank

    There is no longer any separation of evidence items based on the type of the
    evidence item. The same evidence type can now be used when uploading evidence
    from csv files or when adding evidence items in the Evidence bank and all items
    can now be shown in both the Record of Learning and Evidence bank reports.

    By default the Record of Learning report will be filtered to only show evidence
    that was uploaded (i.e. their source is 'Completion history import'). Similarly
    the Evidence bank reports will by default be filtered to only show evidence
    items that were 'Manually created'. As this is a normal report filter, users can
    change / clear the filter to show both uploaded and/or manually created items in
    any one of these reports

  TL-34647 Improved warnings around making changes to facetoface_displaysessiontimezones

Bug fixes
---------

  TL-28799 Updated Weka to include a 'fake' cursor when between blocks

    This is to provide consistency between the block nodes and regular text editing
    in Weka. 

  TL-32891 Allowed report builder toolbar searches to be saved with no standard filters present

    Previously, the 'Save this search' button only appeared in the standard filter
    area, meaning that at least one standard filter needed to be enabled in order to
    save a search.

    The save button is now displayed in the toolbar area when there are no standard
    filters enabled for a report.

  TL-33429 Fixed featured links tile visibility settings when cloning a dashboard

    Prior to this patch, when cloning a dashboard, featured links blocks lost any
    additional visibility restrictions which had been added to a tile. This means
    that if a tile had been limited to a specific audience on the original
    dashboard, the tile on the cloned dashboard would be visible to everybody.

    With this fix, the audience visibility rules for the clone are now consistent
    with the original dashboard.

  TL-34129 Restored evidence imported before migration to their previously used types

    The original migration of imported evidence items resulted in them belonging to
    a single 'Legacy course/certification completion import' system type with the
    original type name stored as a custom field value.

    Previously migrated imported evidence is now restored to belong to their
    original evidence type.

    First time migration will automatically link imported evidence to the correct
    type.

  TL-34144 Fixed Room Name (linked to room details page) column in Seminar reports

    The link did not include information about the session, so when it was followed
    the Custom virtual room link did not display correctly. This has been fixed.

  TL-34167 Fixed Organisation Framework filters using MySQL reserved word
  TL-34235 Set course enrolment date when user is enrolled through Programs or Learning plans
  TL-34241 Fixed the validation of multiple expired Firebase Cloud Messaging tokens while sending a push notification

    When attempting to push notifications to a mobile device, all the mobile devices
    associated with the recipient are fetched and looped through. Previously if one
    of the FCM tokens for a device was not valid, it would be invalidated and the
    loop would be broken, leading to other devices potentially not receiving that
    notification. Now the token is marked as invalid and the loop continues so that
    all devices with a valid FCM token will receive the push notification.

  TL-34244 Fixed videoJS controls in RTL languages

    Fixed videoJS controls in RTL languages so that the play scroller now moves in
    the expected direction.

  TL-34248 Fixed double quote character encoding for Program name report builder column when exporting the data into Excel
  TL-34297 Ensured report builder report created event is triggered when creating from template
  TL-34298 Fixed perform activity static content editing error

    Previously, when a static element was added as a sub element for a linked review
    question, there would be an error when you tried to edit after first creating
    it.

    This patch fixes the error.

  TL-34321 Fixed the context of audience role assignments when the audience is moved

    Previously if a category level audience had roles assigned, and was moved to a
    different category, existing role assignments stayed in the original category
    context. Now the roles will update to the new category context when the audience
    is moved.

  TL-34329 Fixed the position due date link when using the legacy program assignment interface
  TL-34354 Included deletion icals in notifications when seminar sessions are cancelled
  TL-34364 Trigger on-event certification window open notifications at the correct time

    Previously, on-event window open notifications were being triggered when a
    recertification window opened, rather than when the window was supposed to open.
    This led to unexpected behaviour when the opening of a recertification window
    was delayed due to the user being unassigned or suspended. Also, the
    notification was not sent if the certification window was open, which meant that
    the notification would never be sent if it was scheduled to be sent after the
    window open date. The expected behaviour is to always send the notification at a
    date relative to the window open date, regardless of certification status. Note
    that if a user is unassigned or suspended at the time this notification is due
    to be sent, then the notification will not be sent retroactively.

  TL-34403 Prevented the import of evidence for the deleted users

    Prior to this patch, evidence could be uploaded for deleted users when the
    legacy delete option "Keep username, email and ID number (legacy)" is used. This
    is no longer allowed.

  TL-34415 Fixed activity complete notifications created in activity context not being sent

    Activity completion notifications created in an ascendant context of an
    activity, such as the course or system context, were being successfully sent.
    With this fix, activity completion notifications created in the context of a
    specific activity will now also be sent.

  TL-34536 Fixed wrong capability checked for course and activity notification management

    Notification administrators need the 'moodle/course:managecoursenotifications'
    capability to manage course and activity notifications. Previously, the link to
    manage notifications was mistakenly only shown to users who had the
    'moodle/course:update' capability, but the management page would be empty if
    they didn't also have the correct capability.

  TL-34541 Fixed manager's link to program in notifications
  TL-34552 Disable caching in reports that do visibility checks

    Report sources that have been identified as doing visibility checks have been
    updated to remove the option to be cached. Cached data based on those reports
    sources will be removed upon upgrade.

    Any custom report sources which use the post_config_visibility_where function in
    their post_config should also be updated to prevent caching.

  TL-34564 Ensured links on user profile display with correct formatting
  TL-34704 Fixed incorrect language string key for an unavailable course in the mobile app

Technical changes
-----------------

  TL-32931 Updated behat to support PHP 8.0
  TL-33278 Avoid using required column to allow visibility checks in report builder

    Previously, in order to perform visibility checks in reports, we obtained the
    data needed by defining required columns which were columns that, although not
    visible, were present in the report. However it was noted they were interfering
    with aggregation, giving unexpected results.

    Now, "required joins" have been added in order to perform this task. The
    information to do the visibility check is still present, but should not
    interfere with aggregation.

    All applicable report sources have been updated to use the new
    define_requiredjoins function.

    Please note that custom report sources that use the old way of requiring columns
    shouldn't be affected by this change, but we recommend that they are updated to
    use define_requiredjoins to get the correct result when using aggregation.

Tui front end framework
-----------------------

  TL-26667 An error is now thrown for invalid Tui CSS imports, eliminating the confusing in-browser error messages
  TL-34385 Updated the computeError method in FormField.vue to only return the error as a string to prevent an "Invalid Prop" Vue warning.
  TL-34481 Fixed keyboard accessibility of the Dropdown vue component

Library updates
---------------

  TL-34352 Upgraded Video.js to 7.18.1

    Please check any plugins you have installed or written on older versions of the
    video.js plugin

Contributions
-------------

  * Michael Geering at Kineo UK - TL-34297
  * Reported by Nick Wojciechowski, CyberCX Fix contributed by Alex Morris (Catalyst)  - TL-34739



Release 16.1 (27th May 2022):
=================================

Important
---------

  TL-34120 Added disable cron when using maintenance mode

Security issues
---------------

  TL-28575 Removed sesskey from audience dialogue request URLs
  TL-28739 Removed sesskey parameter from jump value on the course view page
  TL-28741 Removed sesskey from the 'Turn editing on' button URL
  TL-28742 Removed sesskey from the course completion report AJAX
  TL-28743 Removed sesskey from URLs in seminar room, asset and facilitator actions
  TL-28744 Removed sesskey from URLs in 'Switch role to' links
  TL-29099 Removed sesskey from URLs in the navigation menu
  TL-33884 Fixed log code to prevent XSS in log descriptions

    Logs generated by some events in Totara could allow XSS in certain situations,
    when viewing either Server > Logs or Server > Live Logs. The fix ensures these
    XSS payloads will not be executed.. This covers both newly generated and already
    existing log entries.

  TL-33890 Prevented accessing profile field badge criteria on a course page by checking accepted criteria types for the current badge (MSA-22-0007 / CVE-2022-0984)
  TL-33926 Converted AJAX request when assigning a company goal to a POST request

    Previously this Ajax request was a GET request, which allowed the sesskey to be
    logged on the server and in browser history.

  TL-33952 Fixed audience-based visibility issue on course-related reports

    The course-based reports ignored the "Audience-based visibility" setting. For
    example, when the course "Audience-based visibility" setting is set to "Enrolled
    users only", it doesn't allow non-enrolled users to see the course details. But
    in course-based reports, such as "Course Membership Report" and "Course
    completion Report", users could see all other course-related entries regardless
    of whether they are enrolled.

    The new changes apply an additional filter to the course based report query to
    check the current user visibility. 

  TL-34336 Prevented cached and/or simultaneous access to the failed login counter (MSA-22-0014 / CVE-2022-30600)

    An issue in the logic used to count failed login attempts could result in the
    account lockout threshold being bypassed by using simultaneous requests.

  TL-34339 Fixed hiddenusefield functionality for user description (MSA-22-0011 / CVE-2022-30597)

Performance improvements
------------------------

  TL-33362 Improved the loading time of the course enrolled users page
  TL-34063 Improved the performance of the user activity page

Improvements
------------

  TL-20269 Added a setting and scheduled task to delete old records from the course completion log

    The course completion log table stores transaction history for the completion
    editor, and can grow very large on sites with a lot of activity. A new 'Delete
    course completion logs after' setting allows admins to automatically cull the
    oldest records from the log. Once those records are deleted, they will no longer
    appear in the completion editor as history.

  TL-25521 Implemented visibility options for site policies

    Site policy visibility can now be set to all users (the default), authenticated
    users only, or guest users only.

  TL-31660 Improved the help text for Seminar third-party email setting
  TL-33365 Changed 'Course compatible in-app' setting to 'Mobile-friendly course' and updated the help text

    When the Totara Mobile app is enabled, courses that are marked as
    'Mobile-friendly' will open in the app; those that are not will be opened in the
    mobile web browser instead. The behaviour of this setting has not changed, only
    the label and help text explaining it.

  TL-33439 Improved the help text regarding the use of event roles in seminar activities
  TL-33498 Fixed missing legacy Session date/time changed message when removing the last session of a seminar

    When the last session of a seminar event is removed, all appropriate users will
    now receive a 'Session date/time changed' message with an ical attachment to
    allow the removal of the calendar entry from their calendars.

  TL-33549 Fixed the cursor styles for disabled inputs
  TL-34051 Added spacing on delete topic confirmation modal body text
  TL-34145 Improved the select/deselect all functionality when looking at the question bank
  TL-34300 Removed broken sorting functionality from the Progress column on the Course completion report

Bug fixes
---------

  TL-30188 Added a warning when editing a role where the role has been assigned in a specific context level
  TL-31206 Fixed deprecation notice on cache admin page
  TL-32604 Added accessible names to report builder learning component links
  TL-33073 Fixed session not being checked when checking sent seminar notifications
  TL-33364 Removed the synchronous audience sync action when saving a course

    Previously, if an audience enrolment was changed when editing a course the
    enrolment of the users in the audiences happened synchronously when saving the
    form. This has been changed so that the sync only happens via the already
    scheduled adhoc task.

  TL-33402 Implemented missing performance activity report response classes
  TL-33510 Made the playlist and engage interactors properly respect the share capability
  TL-33539 Fixed error accessing courses containing activities with invalid availability settings on PHP 7.4+
  TL-33540 Override get_data() to prevent data loss for completion rule
  TL-33560 Prevented sending of performance activity reminder notifications for closed and completed participant instances

    Prior to this patch, reminder notifications could be sent under certain
    circumstances even to participants that had completed their part of a
    performance activity. This patch fixes the bug.

  TL-33602 Added upgrade step to fix dangling temp manager references in job assignment table

    TL-31561 introduced a regression in which temp manager job assignment references
    were not properly nulled in the job assignment table. This patch cleans up those
    references as part of the upgrade process.

  TL-33717 Prevented test course generation for system categories

    This fixes a bug in the test data generator for development sites in
    totara/generator/cli/maketestsite.php. Prior to this patch it could create test
    courses for reserved system categories, leading to error messages in activity
    management and workspace areas.

  TL-33792 Updated the 'Minimum bookings' seminar event setting help text to differentiate it from the 'Notify about minimum bookings' help text
  TL-33844 Added support for multilang filter on hierarchy names in 'Self Registration with Approval' form
  TL-33855 Engage content is no longer lost if there is an error.

    When adding a comment to a workspace or resource, and editing a resource, the
    content would be lost if there was a connection or server error after
    submission. This change ensures content is preserved so that the user can either
    re-submit or preserve the content elsewhere

  TL-33883 Updated the managersubject to not be null during the program/certification notification upgrade 
  TL-33934 Fixed videoJS button display issues in IE11
  TL-33939 Hide role tab in user activities page that have no contents
  TL-33983 Fixed UTF-8 character set handling for MariaDB 10.6
  TL-34029 The Tui modal component now correctly displays button drop shadows

    Within modals button drop shadows were being cropped and the tab order
    incorrectly included some elements

  TL-34035 Fixed discussions appearing multiple times in Workspace discussions when there are many
  TL-34046 Prevented guest user access to the GraphQL mutation 'container_workspace_create_member_request' and fixed some minor issues
  TL-34048 Fixed seminar attendees link placeholder inline help text
  TL-34049 Ensured sheet titles are unique for Excel and ODS when using box/spout library
  TL-34071 Fixed loading display issue and missing table headers on mobile for workspace audiences
  TL-34098 Updated the modal message when deleting a subject instance 
  TL-34103 Removed the legacy email footer from the Totara central notifications
  TL-34104 Reworded language of default seminar notifications for booking request confirmations

    Previously when a booking request was approved there would be default
    notifications which said "Your booking request was approved". The default string
    for this notification has been shortened to "Your booking was approved".

  TL-34106 Removed print button from API documentation page
  TL-34115 Regression with user fullname property fixed in user entity class; solution and test provided by Kineo UK
  TL-34116 Fixed booking event resolver to stop sending notifications to users no longer exist
  TL-34124 Updated report builder display class 'log_serialized_preformated' to ensure data exports correctly
  TL-34141 Fixed that guest should not appear as joined in a workspace
  TL-34142 Fixed incorrect use of bin icon in 'Your playlist'
  TL-34154 Added clearfix class to totara-bar div in table_toolbars.mustache to fix layout issues
  TL-34155 Moved enrolment processing for audience's enrolled learning to an adhoc task in the background

    Prior to this change course enrolments that were required when a course was
    added to, or removed from, an audience's enrolled learning were processed
    immediately. This could lead to exceptionally long times wait for the user who
    initiated the process. The fix for this issue was to shift this processing to a
    background task, these enrolments will now be processed exclusively by cron.

  TL-34157 Fixed custom seminar notifications not being sent for subsequent sessions
  TL-34161 Updated event reservations notifications phpunit test to avoid intermittent failures
  TL-34187 Fixed program and certification notifications sending for each assignment

    Previously, users would receive an "assigned" notification for each assignment
    method that they were included in the program or certification. Now, they only
    get the notification when they are first added to the program or certification,
    and only receive the "unassigned" notification when their last assignment method
    is removed.

  TL-34202 Fixed persistence of Assignment completion criteria

    Fixed the issue with completion criteria of an assignment activity not being
    saved and retained when the activity is either created or viewed.

  TL-34207 Removed suspended users from 'Transfer ownership' search list in workspaces
  TL-34226 Fixed the prevention of adding email attachments when the allowattachments setting is disabled
  TL-34227 Fixed percentage grade calculation when viewing the grader report before importing course completion
  TL-34231 Adding missing CSS for advanced checkbox supplimentary labels
  TL-34234 Ensured '0' value textinput profile fields are displayed on the user profile page
  TL-34236 Ensured that workspaces do not appear in Recent Learning block
  TL-34247 Fixed JavaScript console error when requesting to join/cancel a private workspace
  TL-34306 Fixed JavaScript error when a user tour step was dismissed too quickly
  TL-34330 Fixed due date not being updated when time enrolled was edited
  TL-34332 Fixed sql error when upgrading with existing records in message_metadata
  TL-34353 Added in the additional EU, Canada and Australia endpoints for the Badgr service

Technical changes
-----------------

  TL-34133 The generate_uuid() function has been deprecated

    Please use \core\uuid::generate() instead. If the PECL UUID extension is not
    installed, this new function will use random_bytes() instead of mt_rand() which
    is more secure.

Tui front end framework
-----------------------

  TL-32798 Changed Delete bootstrap icon from Trash fill to Trash outline
  TL-34032 Updated layout of adders to work better on mobile devices
  TL-34151 Fixed keyboard navigation in nested Tui modals

Contributions
-------------

  * Kineo UK - TL-34115



Release 16.0 (26th April 2022):
===============================

New features
------------

  TL-32888 Added modern course and activity notification events

    Several course and activity notification events were added to the modern
    notification system:
    * Learner enrolled in course
    * Learner unenrolled from course
    * Learner completed course
    * Learner completed activity

    Activity completion notifications can be set up in a course or individual
    activities. If it is set up in a course, then notifications will be sent on
    completion of each activity within the course.

    Note that no default notifications have been provided using these new
    notification events. As with other modern notification events, admins can create
    notifications in the site-level notifications interface, which will apply to all
    courses and activities, or they can be created in individual courses or
    activities.

  TL-33032 Added new core 'direct report' relationship for perform activity participants selection

    In response to a number of requests we've added a new 'direct reports'
    participant relationship.

    This relationship selects users as participants where their manager in any job
    assignment is a subject of the activity.

    Of note are the associated changes made to manage closing activities at the due
    date and managing non-participation.  See TL-33053

  TL-33093 Added modern course due date notification event to enable reminder notifications

    A "Course due date" notification event was added to the modern notification
    system. This allows admins to configure "reminder" notifications to be sent
    before or after a user's due date in a course. This notification is only sent if
    the user has not completed the course.

    The course due date for each user is determined by the new "Due date" settings
    found under "Completion tracking" in course settings. "Due date" can only be
    configured when "Enable completion tracking" is enabled in the course. The due
    date is currently used only for the purpose of notifying users, and no other
    action occurs when the due date is reached. Additional functionality may be
    added to it in the future.

    Note that no default notifications have been provided using this new
    notification event. As with other modern notification events, admins can create
    notifications in the site-level notifications interface, which will apply to all
    courses, or they can be created in individual courses.

  TL-33321 Added modern seminar notifications

    Modern notification events and default notifications have been added to
    seminars. Additionally, settings have been provided to allow either a whole site
    or individual seminars to choose between using legacy notifications or modern
    notifications. The combination of settings ensures that notifications cannot be
    sent using both notification systems for the same event, preventing users from
    receiving duplicate notifications, while allowing an easy migration path for
    existing sites.

    A large number of notification events have been added which allow admins to
    configure notifications to be sent from seminars. These notification events
    cover most use cases of the legacy notifications, and they were extended to
    include additional use cases. Default notifications were created based on the
    legacy notifications with enhanced clarity.

    A site-level setting has been added to allow sites to completely disable legacy
    notifications in seminars. When disabled, the legacy notification interfaces are
    hidden. Legacy notifications are disabled by default on new sites, while
    upgraded sites will continue to use legacy notifications until admins choose to
    switch over to use the modern notifications.

    When legacy notifications are enabled at the site level, a new setting within
    each seminar will allow the seminar to send notifications using either the
    legacy or modern notification systems. Admins are able to work with
    notifications in both systems, allowing them to manually migrate customised
    notification content from legacy notifications into modern notifications. Once
    admins are confident that their modern notifications are configured correctly,
    the seminar can be switched to using the modern notifications.

    Previously, it was possible to create additional messages in the legacy
    notification system by going to the Notifications admin page within a seminar
    and selecting "Add". This functionality has been separated into two parts. A new
    admin link "Ad-hoc messages" will allow admins to create and send messages that
    were previously configured with Scheduling set to "Send now", while messages
    that were previously configured with Scheduling set to "Send later" can be
    created using the modern notification system. The "Ad-hoc messages" link is
    available even when legacy notifications are completely disabled.

    Sending notifications to third-party email recipients works differently in
    modern notifications. The list of recipients is still configured in each
    seminar, but now individual notifications must be created as required with the
    recipient set to "Third-party email recipients". No default notifications are
    provided which are set to be sent to the third-party email addresses.

    iCal attachments work differently in modern seminar notifications. When a
    seminar event contains multiple sessions, a single notification will be sent
    with one iCal attachment for each session. The legacy "One message per date"
    setting does not affect this.

  TL-33426 LinkedIn Learning content marketplace activities can be added to courses directly.

    Previously it was only possible to create single-activity courses containing
    LinkedIn Learning activities. It is now possible to choose the 'Content
    Marketplace' activity when editing an existing multi-activity course, and select
    a LinkedIn Learning item to insert as an activity.

    This option appears once LinkedIn Learning has been configured and the content
    catalog has been synced with your Totara site.

  TL-33474 LinkedIn Learning content marketplace is now out of beta

    The LinkedIn Learning feature is now fully available to all customers. This
    marketplace requires a LinkedIn Learning professional subscription. It supports
    browsing, selecting and importing LinkedIn Learning content into Totara,
    launching LinkedIn Learning courses and tracking progress from LinkedIn Learning
    directly within those courses in Totara.

    Also available in 15.3 and later releases.

  TL-33846 Workspaces can now be fully synced with Audiences

    A new "Add Audiences" option now replaces the "Bulk Add Audiences" option in the
    Workspaces owner menu. This option is available to anyone with the capability to
    edit audiences.

    When used, all members of the selected Audiences will be added to the Workspace
    initially. As membership in the Audience changes, members will be added and
    removed from the assigned Workspaces automatically.

    Additionally, the notification sent to users when they are added to a Workspace
    (either by an Audience or by a Workspace Owner directly) can now be edited or
    disabled on the Admin Notifications page.

Important
---------

  TL-34110 Fixed the migration of evidence to learning plan relationships when upgrading to TXP

    Evidence was redesigned in Totara 13, and an entirely new data structure was
    adopted to benefit the evidence feature set. During the upgrade to Totara 13
    (that all sites must go through during the upgrade process)  Evidence data is
    migrated from the pre-13 structure to the new structure.

    The migration moves data item per item, sequentially. Due to a bug in the
    migration process, links between an evidence item and a user’s learning plan
    may be broken, with evidence items being incorrectly related to the incorrect
    learning plan. This mis-mapping can cause evidence items belonging to one user
    to end up being linked to the learning plan of another user. This affects
    historic data only, as all future relations are formed correctly post upgrade.

    The migration has now been fixed to ensure the relationship between an evidence
    item and a learning plan is correctly maintained.

    For sites who have already upgraded the following SQL query can be used to
    determine if your site was affected.
    If a number greater than 0 is returned then please seek support from us and we
    will assist you in confirming the issue, and in supporting you to recover the
    lost relationships.

    SELECT COUNT(dper.id)
    FROM mdl_dp_plan_evidence_relation dper
    JOIN mdl_totara_evidence_item tei ON tei.id = dper.evidenceid
    JOIN mdl_dp_plan dp ON dp.id = dper.planid
    WHERE dp.userid != tei.user_id;

  TL-28247 Updated library GraphQL to version 14.11.3 (includes breaking changes)

    This patch updates the webonyx GraphQL library to version 14.11.3 which comes
    with breaking changes.

    The biggest change is that now all scalar types are validated with strict
    coercion. This means loose variable values, i.e. passing a number as string
    instead of an  expected integer would now throw an exception. Previously this
    was accepted and internally converted.

    If you have created or modified any GraphQL queries or mutations please make
    sure that the scalar types are passed correctly.

    Also some classes got renamed or have changed signatures.

    All important changes are outlined here in detail:
    https://github.com/webonyx/graphql-php/blob/v14.0.0/UPGRADE.md

Security issues
---------------

  TL-30921 Hardened security around data serialization in core.

    Several places in Totara will use the PHP serialize/unserialize functions to
    persist data in different locations. The following places have been changed to
    increase security by limiting what kind of data can be used:
    * The language customisation tool now stores the filters as JSON encoded strings
      across page requests.
    * The Flickr tag block no longer allows objects to be instantiated when the
      results are parsed.
    * The $CFG->custom_context_classes option no longer allows objects to be
      instantiated.
    * The XMLDB Editor stores only the known XMLDB object types across page
      requests.

  TL-33756 Improved validation of badge criteria to prevent SQL injection

    Improved the validation of badge criteria in order to ensure individual criteria
    would correctly fail form validation.

    Also available in 12.41 and later releases.

Performance improvements
------------------------

  TL-31099 Improved performance of the record of learning course report source

    Previously the report source for the "Record of learning: Course" has been using
    a poorly performing base query. This patch introduces a new table which holds
    the record of learning records for each user and is now used as the base for the
    report. This will improve the performance of the report considerably.

    The new table will be automatically filled with the records when a site is
    upgraded. This can take a few minutes and depends on the size of the database.
    The table is also updated when users are enrolled in courses, courses are
    completed or courses are added to learning plans.

    A new task "\totara_plan\task\update_record_of_learning_task" will run every 10
    minutes to keep the new table in sync. In most cases the table will
    automatically be updated through event observers but in rare cases the task will
    need to run to keep the table up-to-date.

    If you experience problems with the Record of Learning report and data not being
    displayed correctly you can adjust the frequency of the task to your needs. The
    runtime of the task depends on the size of the database but so we recommend
    testing its runtime (by running it individually) on the individual site and take
    this into account when adjusting the frequency.

    Apart from the base table the report itself is still working in a fully
    backwards compatible way so any adjustments or embedded reports based on top of
    it will continue to work as before.

    Also available in 15.2 and later releases.

  TL-32776 Improved the performance of the events report source query in seminars.

    Also available in 15.2 and later releases.

  TL-32858 Improved the performance of the user content restriction for direct and temporary reports in the report builder

    Also available in 15.2 and later releases.

  TL-33003 Improved general performance of report exports

    Previously when exporting a report or displaying a large number of records in a
    report display classes for columns were determined for each column and for each
    row. This lead to a lot of unnecessary class resolution calls which can have a
    noticeable impact on the performance of the export or display of the report.
    This patch fixes this and only determines the display class for each column only
    once.

    In addition the display class for relationships have been improved. They now
    cache the result of loading a relationship.

    Also available in 15.2 and later releases.

  TL-33019 Improved performance of the audience report builder content restriction

    Also available in 15.1 and later releases.

  TL-33048 Implemented a cache for \core_component::get_namespace_classes() to improve performance

    Also available in 15.2 and later releases.

  TL-33071 Improved performance of the report used in the completion editor

    Also available in 15.2 and later releases.

  TL-33222 Improved performance of capability checks for managing performance activities or reporting on user's responses

    Previously on a large site the performance of the user activity page could have
    been severely affected due to the use of the has_capability_in_any_context()
    function which is very complex and does not scale very well. 

    In places where we don't necessarily need to call this function we changed it to
    an alternative approach. In other places we optimised the underlying queries so
    that they can perform as fast as possible. On large sites the reports to manage
    participation or report on performance activity responses might still have
    slightly longer loading times but this should be in the normal range for complex
    reports.

    If you are using \mod_perform\rb\util::get_manage_participation_sql() or
    \mod_perform\rb\util::get_report_on_subjects_sql() in your custom code please
    note that the query you are using this function in has to join the context table
    with the user id and the user context level. This change was necessary to reduce
    the number or records the query has to process.

    Also available in 15.2 and later releases.

  TL-33252 Improved the performance of the navigation by optimising notification class loading

    This improves the performance of most pages throughout the site, but is most
    notable in development and testing environments where debugging has been enabled
    or caching has been disabled.
    For production sites it will still have an impact, but it will be less
    noticeable.

    Also available in 15.2 and later releases.

  TL-34017 Searching for a user while sharing a resource now batches keyboard presses

    Also available in 15.6 and later releases.

Improvements
------------

  TL-14090 Introduced the ability for privileged users to archive and reset an individuals progress in a course

    This change introduces two new capability controlled opt-in improvements, and
    improved the access control for the existing archive all users completion
    functionality within courses.

    This allows a user who has the 'totara/core:archivemycourseprogress' capability
    to archive and reset their progress in a course.
    In order to perform the action the user must hold the required capability,
    completion must be enabled for the site and for the course, and the course must
    not be a part of either a program or a certification, and the user must hold a
    completion tracked role within the course.
    A user with this capability will have a see a link to 'Reset this course' within
    the course administration block when viewing the course.
    The new capability is not given to any roles by default.

    It also allows a user who has the 'totara/core:archiveusercourseprogress'
    capability to archive and reset the progress of another user within a course.
    In order to perform the action the user must hold the required capability,
    completion must be enabled for the site and for the course, the course must not
    be a part of a program or a certification, and the user whose progress is being
    archived and reset must hold a completion tracked role within the course.
    A new action has been added to the course completion report to enable this.
    The new capability is not given to any roles by default.

    Finally, the existing archive and reset completion functionality now has a new
    dedicated capability 'totara/core:archiveenrolledcourseprogress'.
    Previously users were required to have the ability to delete the course in order
    to archive and reset progress for all enrolled users.
    To ensure backwards compatibility during upgrades when the new capability is
    installed roles that hold the moodle/course:delete capability are given this
    permission.

    Also available in 15.1 and later releases.

  TL-22564 Standardised up and down chevrons

  TL-25962 Added the ability to remove a link from Weka while maintaining the link text

    Also available in 15.6 and later releases.

  TL-27465 Page style improvements made on the 'your workspaces' page

    Made several minor cosmetic improvements to the 'your workspaces' page such as
    white spacing and content alignment

  TL-28408 Amended content spacing on the manage performance activities tabs

  TL-30456 Added a way to define a common plugin name for notification grouping

    Prior to this patch, the grouping of notifications in the modern notification
    administration was done by the language string for the notifications' component
    names. With this patch, this name can be overridden for a component by adding a
    language string with the key 'pluginname_totara_notification'.

    This has been applied to the program component whose notifications are now
    grouped under 'Program', replacing the previous name 'Program management'.

    Also available in 15.3 and later releases.

  TL-30666 Resized the default thumbnail images for course, program, certification and resource in Ventura theme 

  TL-30928 Changed webservice exception type from servicenotavailable to sessionerroruser for call_external_function() when the user is not logged in

  TL-31252 Options to manage relationship participant instances dynamically

    Participants are added to performance activities as individuals associated with
    Performance activity roles e.g. peer, or as formal relationship roles e.g.
    manager, appraiser.  The relationship roles are defined within the job
    assignments for the subject.

    As changes occur to personnel in an organisation people move around in their
    roles.  Some organisations mandate regular changes of management.

    {color:#172b4d}Currently changing participation in an activity is a manual
    task.{color}

    {color:#172b4d}These changes provide options for Admins to define how users
    exiting activity relations and roles will be have their participant instances
    closed and or hidden (or not) from an activity.  And that users entering
    activity relationships and roles will be have participant instances added to the
    activity.{color}

    There are two new options in the 'Perform settings' section of the feature
    configuration:
    * Enable role change participant instance creation
      When enabled, this will create a new participant instance for existing open
      activities when a new user enters a relationship for that activity.

    * Enable role change participant instance closure
      When enabled, this will close participant instances for open activities when a
      user leaves a relationship for that activity.

    {color:#172b4d}These settings can be applied Globally to all activities in the
    Perform settings.{color}

    {color:#172b4d}Admins can override these default settings in an individual
    activity's config.  {color}

    The intention is that these transitions can be largely automated

  TL-31523 When a LinkedIn Learning course is created from a specific category, the catalog import page now uses the category as the default category for selected items.

  TL-31706 Added OAuth2 authentication to outgoing SMTP mail service

    With this patch outgoing email connections can now be configured using the
    XOAuth2 protocol. To make use of this protocol you will need to configure an
    OAuth2 service with your provider and connect with a system account. Afterwards
    the OAuth2 service can be chosen on the Outgoing email configuration page.

    Also available in 15.1 and later releases.

  TL-31869 Improved form error notifications when editing programs

    Also available in 15.3 and later releases.

  TL-32087 An administrator can delete a client provider on client provider settings

    Also available in 15.2 and later releases.

  TL-32088 Oauth2 providers can now be added via the user interface

    Oauth2 providers can be added to the system by administrators. As part of this
    change the task that automatically created a LinkedIn Learning provider has been
    removed - the administrator must now manually add the provider when setting up
    the integration.

    Also available in 15.2 and later releases.

  TL-32118 Updated data type of fields in Workspaces Engagement report to make them graphable

    The updated fields are:
    * Discussions
    * Comments in discussions
    * Linked playlists
    * Linked resources
    * Files
    * Members

    Also available in 15.2 and later releases.

  TL-32167 Updated performance activities page filters and current tab to be persistent across page loads

  TL-32253 Improved behaviour of closing attempts for timed quizzes

    This pulls in 5 patches for the quiz module from Moodle:
    MDL-65864 question engine: fix re-saving a new usage
    MDL-54907 quiz generator: defaults should match a new Moodle install
    MDL-66685 questions: should able to save an empty question usage
    MDL-54907 quiz: better timefinish for attempts finished asynchronously
    MDL-68970 quiz: prevent page caching during attempts

    Also available in 15.3 and later releases.

  TL-32343 UI improvements on indentation and hover state of the administration tree component

    Also available in 15.2 and later releases.

  TL-32356 Report Builder description of the report is now displayed when exporting to PDF

  TL-32367 Added a new option "--is-pending" to the upgrade cli script

    The new option enables admins to detect whether an upgrade is necessary without
    actually running the upgrade. This can be useful for pre-upgrade checks or
    automation.

    Also available in 15.2 and later releases.

  TL-32473 Allow manual deletion of instances of a performance activity

    In 15.4 we added the ability to delete subjects and participants from a
    performance activity.  Because this addressed a privacy risk the change has
    been backported to the last stable version of all major releases, so that it is
    available to all version of Perform.

    In version 16 we have made a small design improvement to shift all the
    participation controls into an ellipsis menu, so this is complies with the
    Totara visual design.

    Also available in 15.4 and later releases.

  TL-32490 Added a new filter to show or remove already imported courses in the content marketplace catalog import

    Also available in 15.2 and later releases.

  TL-32592 Implemented truncating the title of the performance activity in the task card on the activity page

    Also available in 15.2 and later releases.

  TL-32624 Improved workflow for users requesting to join a private workspace, and a new notification to users if the request was declined

    * Added pending request status and 'cancel' button to workspace card in Find
      workspaces
    * Added option for users to include a personal message to workspace owner in the
      request
    * Added option for workspace owner to include a personal message when declining
      a request
    * Added a new notification to inform users when their request was declined

  TL-32718 Added the operation name to single GraphQL URLs for easier debugging

  TL-32728 Improved the Atto editor auto-sizing to match the textarea(s) it replaces

  TL-32756 Added a progress bar to LinkedIn Learning activities

    This progress bar will reflect progress towards completion of the external
    activity, as long as the customer's LinkedIn Learning account has been
    configured to send progress events.

  TL-32788 Added additional spacing when viewing a forum within a course

    Also available in 15.2 and later releases.

  TL-32824 Added the ability for administrators to see a list of all OAuth 2 providers 

    Also available in 15.2 and later releases.

  TL-32870 Added a new 'For use in' column to the 'Manage evidence types' report source

    This column indicates which types can be used in completion record imports, or
    while adding evidence manually. This column will be added to default reports for
    new installs, upgrading sites will have to add this column manually or reset the
    report.

  TL-33005 Added the ability to authenticate incoming email using OAuth2

    Also available in 15.3 and later releases.

  TL-33100 Improved the display of individual notifications while modifying

    Previously individual notifications were organised in a table. Now cards are
    used instead of that table.

  TL-33113 Converted notification override checkbox to a toggle

  TL-33118 Added an information notification to the manage performance activities page while trying to view section elements without responding participants

  TL-33141 When an administrator manually overrides LinkedIn Learning activity completion to complete, the progress bar on the activity now shows as 100%

    Also available in 15.4 and later releases.

  TL-33144 Improved the spacing between components on the LinkedIn Learning course activity page

    Also available in 15.4 and later releases.

  TL-33187 Added an accessible label to the saved search dropdown in reports

    Also available in 15.2 and later releases.

  TL-33212 Updated HR import completed log entries to include the element name

    The element name associated with the HR Import run is now shown to the user.

    Also available in 15.3 and later releases.

  TL-33219 Added a new ‘totara/oauth2:manageproviders’ capability to the system administrator default role that allows them to manage the OAuth2 providers

    Also available in 15.2 and later releases.

  TL-33298 Changed legacy user dialogues to display fields enabled in 'showuseridentity' setting  

    Previously the dialogue to select users in the following locations did not take
    the fields configured in the 'showuseridentity' setting into account but always
    showed only the full name and the email address of the users:

    * Job assignments (choosing appraiser, manager and temporary manager)
    * Seminars (choosing approvers and internal user for facilitator)
    * Program assignment (choosing an individual to assign)
    * Scheduled report (Choose system users to send report to)
    * Feeback 360 - legacy (Choose recipients for feedback request)

    With this patch all these dialogues are now showing the additional fields
    configured in the 'showuseridentity' setting. Additional fields like idnumber or
    department can now be displayed as part of the user information to help
    identifying individual users.

    If a site has not changed this setting nothing changes as email is already
    enabled by default. If the setting deviates from the default then after upgrade
    additional information might be shown to users who have the
    "moodle/site:viewuseridentity" capability. This is consistent to other parts of
    the system.

  TL-33302 Moved the 'audience-based visibility' setting away from the 'enrolled audiences' setting on the 'Edit course' page

    This is to help prevent configuration errors due to their associated labels.

    Also available in 15.3 and later releases.

  TL-33304 Improved the wrapping of page layouts when titles contain long words

    Also available in 15.3 and later releases.

  TL-33378 Improved the use of browser's 'back' button in resources to lead back to the 'library' tab

  TL-33419 Added more user placeholder options to modern notifications

    This change extends the list of user placeholders in notifications by adding: 
    * User's ID Number.
    * User's address.
    * User's description.
    * User's institution.
    * User's language.
    * User's Skype ID.
    * User's phone number.
    * User's mobile phone number.
    * User's URL

  TL-33442 Added success notification toasts for all UI toggles on manage performance activity notifications tab

  TL-33533 Gave more visual prominence to the performance activity participant selection banner

  TL-33547 Improved layout of labels in legacy course activity adders

    Also available in 15.5 and later releases.

  TL-33550 Added support for completion archiving to lesson modules

    Previously the lesson module did not support completion archiving, this meant
    that if you used the "Reset completions" functionality under "Course
    administration" that lesson data persisted. Support for this has been added and
    now if you reset completions for a course it will also remove lesson timing
    data, lesson attempt data, and lesson overrides for completed users in the
    course.

    Also available in 15.5 and later releases.

  TL-33566 Added list columns to the My Bookings report

    Three new columns have been added to the 'My Bookings' report to display the
    facilitators, rooms and assets linked to a specific session as lists instead of
    adding duplicate rows for a single session that have multiple facilitators,
    rooms or assets.

    Also available in 15.6 and later releases.

  TL-33595 Fixed goals not working when multi tenancy was enabled

    Previously, goals functionality was not fully working when multi tenancy was
    enabled, especially if isolation mode was enabled. With this patch goals are now
    working within the restrictions of multi tenancy. This means that management of
    company goals can only be done with system permissions by system users. Company
    goal assignment and personal goals management can be done by tenant users or
    their managers (depending on how the permissions are set up). There are some
    restrictions if isolation is enabled, the viewing of company goals or goal
    frameworks is not possible as tenant users and the links to these pages are not
    shown in this case.

    Also available in 15.5 and later releases.

  TL-33654 Added information about changes to appraiser and temporary manager to job assignment events

    Added data for old & new appraiser as well as old & new temporary manager to the
    created, updated and deleted events for job assignments . This is to enable
    observers to detect changes to a job assignment's appraiser or temporary
    manager.

  TL-33679 Changed form action to 'GET' for manage dashboard single button

    Also available in 15.6 and later releases.

  TL-33733 Add additional options to instance creation reference dates on a performance activity to support creating instances based on the closure of another activity.

    The new options are:
    * Close date of another activity instance
    * Close or completion date of another activity instance (whichever is sooner)

  TL-33734 Added closure trigger types for repeating instances on performance activities

    Added closure trigger types for repeating instances and updated the UI to
    accommodate this change.

    This adds a 'closed_at' column for the perform_subject_instance table. The
    upgrade process populates this column with the latest subject instance closed
    event timestamps from the logstore_standard_log table if there are any.
    Otherwise the column value is the upgrade time.

  TL-33735 Updated the UI of the 'activity job assignment-based instances' setting on manage performance activities page

  TL-33737 Updated the UI of the due date setting on manage performance activities page

    Moved the enable toggle within the due date block and refactored the component
    to handle it's own validation

  TL-33871 Added notification preference and recipient/target user to resolver's file attachment request

  TL-33970 Added appropriate CSS class to topics format General section when header colour is enabled

    Also available in 15.6 and later releases.

  TL-33975 Increased the top and bottom margin of the 'Load more' button in the Adder.vue component

    Also available in 15.6 and later releases.

  TL-33977 Added a visual loading state to the create button of the performance activity creation modal

  TL-33992 Changed button text on confirm model in playlist

  TL-34102 The totara_mobile_certification query now returns a 'viewable' flag for all courses in certifications

    Also available in 15.6 and later releases.

  TL-34113 Fixed that workspaces show as Courses in Audience under enrolled Learning tab

  TL-34114 Updated the OriginalSpaceCard TUI component to focus the action button when closing modals on the "Find workspaces" page

    Also available in 15.6 and later releases.

Accessibility improvements
--------------------------

  TL-31867 Improved keyboard accessibility when using the Dropdown menu

Bug fixes
---------

  TL-23318 Catalog cards are now closer in appearance to engage resource cards

  TL-27410 Removed the automatic scroll when clicking on the edit pencil on a perform element

  TL-30977 Fixed an issue where the redisplay element incorrectly showed ‘your response’ when the current user hasn’t provided a response

  TL-32766 Update user progress when a user is enrolled in a course via external content marketplace

  TL-33390 Fixed an issue where the browser back and forward buttons didn't update the performance activity filters

  TL-33449 Fixed error when closing subject instances after a session timeout

  TL-33972 Fixed class path for grading report unit test

  TL-33982 The modal now closes immediately when removing assignments from a performance activity

  TL-33990 Fixed joined dropdown in workspace to not being disabled

  TL-34004 Fixed that the request and decline message modal will close accidentally

  TL-34005 Added additional space between request and buttons when viewing requests to join a workspace

  TL-34016 Improved assign users modal loading state

  TL-34022 Fixed hyphenation issue and refactored engage workplace request

  TL-34031 Fixed resource styling at smaller viewports

  TL-34050 Added loading state to existing resource modal

  TL-34066 Updated OriginalSpaceCard.vue to dismiss the confirmation modal after leaving a workspace from the 'Find workspaces' page

  TL-34074 Fixed Grades: Outcomes page formatting

  TL-34096 Changed default background colour in email template to Ventura default

Technical changes
-----------------

  TL-28678 Removed deprecated functions in javascript-static

    There were a number of functions that were deprecated in Totara 12 which were
    throwing a JavaScript error or warning when called. These have now been removed

  TL-30246 Updated the profileimage field in the mobile user_own_profile query to return null when empty

  TL-32041 Added configurable number filter to report builder

    This filter allows filtering numerical data using an operator that is specified
    in the report source.

    Also available in 15.1 and later releases.

  TL-32155 Added ability to disable grouping for a column in a report source

    Also available in 15.1 and later releases.

  TL-32613 Improved tile layout on workflow manager page

    Also available in 15.1 and later releases.

  TL-32764 Added a new 'get_progress' function to the activity course completion criterion

    The progress of an activity course completion criterion is now extracted from
    the 'progress' field where the information is available.

    Also available in 15.2 and later releases.

  TL-32840 The totara_mobile_program query now returns a 'viewable' flag for all courses in the program

    Also available in 15.4 and later releases.

  TL-32894 Removed deprecated code in /admin/ directory

  TL-32968 Added support for activity completion progress

    With this change it is possible for activity modules to optionally set a
    percentage progress towards activity completion, in order to make it possible to
    report on more fine grained progress towards completing an activity.

    At this point the new API is not implemented for any activities yet, and there
    is no interface changes which display activity progress.

    This change includes a database upgrade to add a new "progress" field to the
    "course_modules_completion" table. The new field supports values between 0 and
    100. Existing records will be given a "progress" of "null".

    Also available in 15.1 and later releases.

  TL-33054 Added support for entity snapshots to events

    Also available in 15.2 and later releases.

  TL-33070 Refactored xAPI receiver code to avoid need to specify component that will handle the request

    The xAPI statement receiver endpoint no longer expects the component to be
    specified - instead the receiver handles authentication and basic validation
    then triggers a xapi_statement_created event that can be handled by any
    component or plugin.

    The existing LinkedIn Learning code was updated to make use of the new event.

    When configuring xAPI reporting in LinkedIn Learning it is no longer necessary
    to include the component parameter in the xAPI Server URL field.

    Also available in 15.2 and later releases.

  TL-33094 Moved subject and manager recipient classes to core for increased re-usability

    Also available in 15.2 and later releases.

  TL-33124 Activity modules can now introduce notifications via modern notifications

    Notifiable events can be added to all context levels. However, the navigation
    system does not provide links to add, edit, delete custom notifications at these
    levels.
    This change introduces a manage notification link to the activity settings
    branch in the navigation tree providing there is at least one relevant resolver.

    Also available in 15.2 and later releases.

  TL-33375 Deprecated totara_tenant\entity\tenant

    totara_tenant\entity\tenant is a duplicate of core\entity\tenant

  TL-33380 Allow notifications to be sent to third-party email recipients

    The new notifications system has been improved to allow recipient groups to
    return lists of virtual users. These virtual users should each contain a valid
    email address. When the recipient group is selected in a notification, the
    notification will be sent to these email addresses.

  TL-33422 Fixing incompatible function return type signatures in preparation for PHP 8.1 support.

  TL-33454 Improved the email_to_user() function to allow for multiple file attachments in a single email

    This change allows multiple attachments to be passed through to the
    'email_to_user()' function by passing the attachments through in the new
    attachment_list parameter. This list should contain [attachment_name =>
    attachment_file]

  TL-33457 Added support for customfield placeholders in notifications

  TL-33470 Updated the minimum Totara server and database requirements

    We have updated the supported server versions for Totara 16.
    * The minimum PostgreSQL version has been increased to 10. PostgreSQL 9.6 and
      below are no longer supported.
    * The minimum MariaDB version has been increased to 10.3.17. MariaDB 10.2 and
      below are no longer supported.
    * The minimum Apache version has been increased to 2.4. We do not recommend
      running Totara on the Apache 2.2 line.
    * The maximum PHP version is set to 8.0.x. PHP 8.1 and above are not yet
      supported.
    * PostgreSQL 14 support has been added. The enable_memoize option must be
      disabled in your database configuration.
    * MariaDB 10.6 support has been added. The innodb_read_only_compressed option
      must not be enabled.

    The full list of server requirements are outlined in the readme file.

    The Environment Check page has been updated in Totara 13 and above and can be
    used to check if your setup meets the minimum server requirements for Totara 16.

    Also available in 15.4 and later releases.

  TL-33494 Configured pytest to log with the xmlunit1 log format for the recommendations system unit tests.

    Also available in 15.3 and later releases.

  TL-33727 Incremented web browser supported versions

    Official support for Edge Legacy (Edge <= 18) has been dropped, and the
    officially supported minimum Safari version has been increased to 13.1.

  TL-34000 Changed name field format to 'PLAIN' for 'totara_oauth2_client_provider' type

    Also available in 15.6 and later releases.

  TL-34168 Updated the minimum supported versions for MariaDB and MySQL

    The readme has been updated with explicit minimum versions for both MariaDB and
    MySQL.

    MariaDB we have set the minimum to the first stable release of each line. Alpha
    and beta release versions are not supported.

    MySQL 8.0.1 is the minimum. MySQL 8.0 does not include the required encoding
    Totara needs.

    In all cases, we recommend using the latest version in each database release
    line.

    Also available in 15.6 and later releases.

Tui front end framework
-----------------------

  TL-25683 Removed file paths from jest descriptions

  TL-25928 Weka editor now has the same focus outline as other inputs

    Also available in 15.4 and later releases.

  TL-26643 Added a optional large set size popover setting

    Added a setting to allow popovers to be set to a large size for correctly
    displaying larger amounts of content.

  TL-30088 Uploaded videos have the same max width as YouTube or Vimeo Videos

    Also available in 15.4 and later releases.

  TL-30760 Updated CheckboxGroup and RadioGroup Tui components to trigger required validation on blur

    Also available in 15.4 and later releases.

  TL-30762 Updated TUI Select and FormSelect components to correctly trigger required validation on blur

    Also fixed issue where 'touch' wasn't included in the FormScope Reform element
    reformScope.

    Also available in 15.3 and later releases.

  TL-31255 Uniform date selector now shows validation immediately after entering a value

    Previously this was only shown once the form was attempted to be submitted

    Also available in 15.4 and later releases.

  TL-31967 Fixed issue where collapsing the tables on the 'manually rate competencies' page would remove the table header

    Also available in 15.5 and later releases.

  TL-32441 Updated produce() immutable helper to support objects frozen with Object.freeze(), such as Apollo results

    Also available in 15.2 and later releases.

  TL-32542 Added an open in new window setting when creating a link in Weka

  TL-32619 Use ConfirmationModal for Engage confirmation modals

  TL-32695 Replaced 'char length' field on the NotepadLines page in the Tui samples library with a select list

    Also available in 15.1 and later releases.

  TL-32823 Updated the form/Radio Tui sample component to only include relevant sample props

    Also fixed the sample props that weren't yet bound to the component

    Also available in 15.2 and later releases.

  TL-32995 Prevented moving an item to the same position on the dragdrop component

    Also available in 15.1 and later releases.

  TL-33000 Fixed accessibility issue with disabled buttons

    Also available in 15.1 and later releases.

  TL-33197 Added "contextMode" prop to LabelledButtonTrigger with default set to "uncontained" to fix display issues with long names

    Also added hyphenation to text in LikeRecordsList for overflowing names

    Also available in 15.3 and later releases.

  TL-33349 Updated Collapsible to add new visual style options

  TL-33389 Updated Table to take a headerHasLoaded prop so the header will render while the body loads

  TL-33407 Changed delete comment modal to use ConfirmationModal

  TL-33413 Fixed the handling of null in tui/immutable produce()

    Previously, returning null in produce() was the same as not returning anything.
    It is now treated as returning a value, so it is now possible to have `null` as
    the result of a recipe.

    Also available in 15.4 and later releases.

  TL-33466 Fixed encapsulation of Vue CSS in notifications

  TL-33497 Fixed error modal "copy all" in IE 11

    Also available in 15.5 and later releases.

  TL-33528 Updated the ToggleSwitch TUI component to match the design spec when disabled

    Also updated the colours to be inline with the current design and updated the
    TUI samples page to allow for testing of the disabled setting

  TL-33554 Improved display when changing the time on a video added with Weka

    Also available in 15.4 and later releases.

  TL-33572 Fixed visual clipping on the ParticipantGeneralInformation TUI component

    Also available in 15.5 and later releases.

  TL-33687 Changed HTTP method from POST to GET when loading the data for a Totara dialogs

    Also available in 15.6 and later releases.

  TL-33759 Fixed Reform displaying errors on touched fields before validations were complete

  TL-33920 Fixed an issue where importing Vue in ext_ Tui components would end up with a separate copy of Vue instead of referencing the one in the core bundle

    Also available in 15.6 and later releases.

  TL-33921 Implemented triggering an error to prevent the compilation of large SCSS files containing non-ASCII characters on PHP < 7.4 to hang

    Due to PHP bug 72685, versions of PHP before 7.4 cannot efficiently run Unicode
    regexes over large strings. We work around this by removing non-ASCII characters
    from comments to avoid hitting the Unicode path where possible, and by erroring
    instead of hanging on large files in order to communicate the source of the
    problem.

    This behavior can be disabled through the "Allow unperformant CSS" option in the
    Tui frontend framework settings.

    Also available in 15.6 and later releases.

Recommendations engine
----------------------

  TL-33600 Fixed the headers of exported `item_data_x.csv` files for the recommendation engine

    The headers that related to the course tags and engage topics were being
    prefixed with 'topic_' string. This would cause buggy computations in the
    recommendation engine when some tags and labels shared the same name. This bug
    is fixed so tags and labels will have relevant prefixes 'tag_' and 'topic_',
    respectively.

    Also available in 15.5 and later releases.

  TL-33681 The warning issue with the new APScheduler package is resolved

    Also available in 15.5 and later releases.

  TL-33682 Fixed the favicon issue after Flask upgrade

    Also available in 15.5 and later releases.

  TL-33696 Updated the requirements file of the recommendations engine with specific library versions

    Prior to this change only directly added libraries were recorded in the
    requirements.txt. Any dependencies for these libraries would load the most
    recent version it could, which could result in things breaking if a new version
    introduced a breaking change and did not report it correctly.

    With this patch, we have pinned all dependencies to specific versions that we
    have tested works with the recommendations engine.

    Also available in 15.5 and later releases.

Library updates
---------------

  TL-28214 Updated PHPSpreadsheet library to version 1.19.0

  TL-28228 Updated library xhprof to version 2.3.4

  TL-28235 Moved library SCSSPHP to composer

  TL-28238 Updated library GeoIP2 to version 2.11.0

    Also moved into /libraries/required and to use composer

  TL-28242 Upgraded the window.fetch polyfill library to version 3.6.2

  TL-28257 Updated DOMPdf library to version 1.0.2

  TL-28258 Updated library SVGGraph to version 2.30.0

  TL-28259 Upgraded PHPSec library to version 2.0.35

  TL-28260 Updated library jQuery Datatables to 1.11.3

  TL-28261 Updated library Flexitour to 0.12.3

  TL-28262 Updated popper.js to 1.16.1-lts

  TL-28266 Moved library setasign/fpdi to composer

  TL-28267 Updated prosemirror libraries to latest versions

  TL-28269 Updated date-fns library to version 2.24.0

  TL-28272 Updated library Vue to version 2.6.14

  TL-31405 Updated library lessphp to version 3.1.0

  TL-31637 Upgraded Flask library from 1.1.2 to 2.0.3

    Also available in 15.4 and later releases.

  TL-32263 Updated PEAR core library to version 1.10.13

  TL-32317 Updated library core-js to version 3.19.1

  TL-33077 Updated FPDI-1.6.2 library to support PHP8.x

  TL-33555 Imported mustache library version 2.14.1

Contributions
-------------

  * Brad Simpson at Kineo USA - TL-33547
