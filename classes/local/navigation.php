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

namespace tool_flexaccess\local;

/**
 * The one definition of the FlexAccess tab structure, for the system and the course scope.
 *
 * Tabs follow administrative tasks rather than PHP endpoints. A tab is only offered when the
 * current user holds at least the read capability of its function; write actions stay separately
 * protected on the pages themselves. Existing page URLs are kept, so deep links keep working.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class navigation {
    /** Main tab: overview (dashboard). */
    public const OVERVIEW = 'overview';
    /** Main tab: users. */
    public const USERS = 'users';
    /** Main tab: access lists. */
    public const ACCESSLISTS = 'accesslists';
    /** Main tab: invitations and campaigns. */
    public const OUTREACH = 'outreach';
    /** Main tab: policies. */
    public const POLICIES = 'policies';
    /** Main tab: system status. */
    public const STATUS = 'status';
    /** Course tab: restrictions. */
    public const RESTRICTIONS = 'restrictions';

    /**
     * The system-scope tab definition, filtered by the current user's capabilities.
     *
     * @return array<string, array> Main tab id => [url, label, subtabs => [id => [url, label]]].
     */
    public static function system_tabs(): array {
        $system = \context_system::instance();
        $can = static fn(string $cap): bool => has_capability('tool/flexaccess:' . $cap, $system);
        $url = static fn(string $page): \moodle_url => new \moodle_url('/admin/tool/flexaccess/' . $page);
        $str = static fn(string $id): string => get_string($id, 'tool_flexaccess');

        $definition = [
            self::OVERVIEW => [$str('taboverview'), [
                'dashboard' => ['viewdashboard', $url('index.php'), $str('taboverview')],
            ]],
            self::USERS => [$str('tabusers'), [
                'accounts' => ['viewaccounts', $url('accounts.php'), $str('tabusers')],
            ]],
            self::ACCESSLISTS => [$str('tabaccesslists'), [
                'batches' => ['managebatches', $url('batches.php'), $str('tabaccesslists')],
            ]],
            self::OUTREACH => [$str('taboutreach'), [
                'invitations' => ['manageinvitations', $url('invitations.php'), $str('invitations')],
                'campaigns' => ['managecampaigns', $url('campaigns.php'), $str('campaigns')],
            ]],
            self::POLICIES => [$str('tabpolicies'), [
                'policyoverview' => ['viewpolicies', $url('policies.php'), $str('tabpolicyoverview')],
                'managepolicies' => ['managepolicies', $url('managepolicies.php'), $str('managepolicies')],
            ]],
            self::STATUS => [$str('tabstatus'), [
                'systemcheck' => ['viewsystemstatus', $url('status.php'), $str('tabsystemcheck')],
                'mailqueue' => ['managemailqueue', $url('mailqueue.php'), $str('mailqueue')],
            ]],
        ];
        $tabs = [];
        foreach ($definition as $id => [$label, $subs]) {
            $visible = [];
            foreach ($subs as $subid => [$cap, $link, $sublabel]) {
                if ($can($cap)) {
                    $visible[$subid] = [$link, $sublabel];
                }
            }
            if (!$visible) {
                continue;
            }
            $first = reset($visible);
            $tabs[$id] = [
                'url' => $first[0],
                'label' => $label,
                // A single sub-page is the tab itself; only real groups show a second row.
                'subtabs' => count($subs) > 1 ? $visible : [],
            ];
        }
        return $tabs;
    }

    /**
     * The course-scope tab definition, filtered by the current user's capabilities in the course.
     *
     * @param int $courseid Course id.
     * @return array<string, array> Tab id => [url, label, subtabs => []].
     */
    public static function course_tabs(int $courseid): array {
        $context = \context_course::instance($courseid);
        $tabs = [];
        if (has_capability('tool/flexaccess:viewcourseusers', $context)) {
            $tabs[self::USERS] = [
                'url' => new \moodle_url('/admin/tool/flexaccess/course.php', ['courseid' => $courseid]),
                'label' => get_string('tabusers', 'tool_flexaccess'),
                'subtabs' => [],
            ];
        }
        if (batch::can_request($courseid) || batch::can_view($courseid)) {
            $tabs[self::ACCESSLISTS] = [
                'url' => new \moodle_url('/admin/tool/flexaccess/coursebatches.php', ['courseid' => $courseid]),
                'label' => get_string('tabaccesslists', 'tool_flexaccess'),
                'subtabs' => [],
            ];
        }
        if (has_capability('enrol/flexaccess:config', $context)) {
            $tabs[self::RESTRICTIONS] = [
                'url' => new \moodle_url('/enrol/flexaccess/restrictions.php', ['courseid' => $courseid]),
                'label' => get_string('tabrestrictions', 'tool_flexaccess'),
                'subtabs' => [],
            ];
        }
        return $tabs;
    }

    /**
     * Render the system tab bar with the given tab (and optional sub-tab) active.
     *
     * @param string $active Main tab id.
     * @param string|null $sub Sub-tab id, when the page belongs to a group.
     * @return string HTML.
     */
    public static function render_system(string $active, ?string $sub = null): string {
        return self::render(self::system_tabs(), $active, $sub);
    }

    /**
     * Render the course tab bar with the given tab active.
     *
     * @param int $courseid Course id.
     * @param string $active Tab id.
     * @return string HTML.
     */
    public static function render_course(int $courseid, string $active): string {
        return self::render(self::course_tabs($courseid), $active, null);
    }

    /**
     * Tab bar for a page that belongs to one course's access lists (batch detail pages).
     *
     * Site-wide batch managers keep the system navigation; course managers see the course tabs.
     *
     * @param int $courseid Course the batch belongs to.
     * @return string HTML.
     */
    public static function render_accesslist(int $courseid): string {
        if (has_capability('tool/flexaccess:managebatches', \context_system::instance())) {
            return self::render_system(self::ACCESSLISTS);
        }
        return self::render_course($courseid, self::ACCESSLISTS);
    }

    /**
     * Build and render a tab tree.
     *
     * @param array $tabs Tab definition.
     * @param string $active Main tab id.
     * @param string|null $sub Sub-tab id.
     * @return string HTML.
     */
    private static function render(array $tabs, string $active, ?string $sub): string {
        global $OUTPUT;
        if (!$tabs) {
            return '';
        }
        return $OUTPUT->render(self::tree($tabs, $active, $sub));
    }

    /**
     * Build the tab tree for a definition with the given tab (and sub-tab) selected.
     *
     * @param array $tabs Tab definition ({@see self::system_tabs()} or {@see self::course_tabs()}).
     * @param string $active Main tab id.
     * @param string|null $sub Sub-tab id.
     * @return \tabtree
     */
    public static function tree(array $tabs, string $active, ?string $sub = null): \tabtree {
        $objects = [];
        foreach ($tabs as $id => $tab) {
            $object = new \tabobject($id, $tab['url'], $tab['label']);
            foreach ($tab['subtabs'] as $subid => [$link, $label]) {
                $object->subtree[] = new \tabobject($subid, $link, $label);
            }
            $objects[] = $object;
        }
        $selected = ($sub !== null && isset($tabs[$active]['subtabs'][$sub])) ? $sub : $active;
        return new \tabtree($objects, $selected);
    }
}
