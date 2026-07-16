<?php
/*
 * This file is part of Totara Learn
 *
 * Copyright (C) 2019 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Riana Rossouw <riana.rossouw@totaralearning.com>
 * @package criteria_coursecompletion
 */

namespace totara_competency\entity;

/**
 * Course entity
 *
 * @property-read int $id ID
 * @property int $category Category ID
 * @property int $sortorder Sortorder
 * @property string $fullname Course full name
 * @property string $shortname Course short name
 * @property string $idnumber Unique idnumber
 * @property string $summary Course summary
 * @property int $summaryformat Summary format
 * @property string $format Course format
 * @property int $showgrades Show grades?
 * @property int $newsitems
 * @property int $startdate Start date
 * @property int $enddate End date
 * @property int $marker
 * @property int $maxbytes
 * @property int $legacyfiles
 * @property int $showreports
 * @property int $visible
 * @property int $visibleold
 * @property int $groupmode
 * @property int $groupmodeforce
 * @property int $defaultgroupingid
 * @property string $lang
 * @property string $calendartype
 * @property string $theme
 * @property int $timecreated
 * @property int $timemodified
 * @property int $requested
 * @property int $enablecompletion
 * @property int $completionstartonenrol
 * @property int $completionprogressonview
 * @property int $completionnotify
 * @property int $audiencevisible
 * @property int $cacherev
 * @property int $coursetype
 * @property string $icon
 * @property string $containertype
 * @property int $duedate
 * @property int $duedateoffsetamount
 * @property int $duedateoffsetunit
 *
 * @method static course_repository repository()
 *
 * @package totara_competency/entity
 */
class course extends \core\entity\course {

    /**
     * @return string
     */
    public static function repository_class_name(): string {
        return course_repository::class;
    }

}
