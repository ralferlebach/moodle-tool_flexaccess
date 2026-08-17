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
 * Administration navigation for tool_flexaccess.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category('tool_flexaccess_category', get_string('pluginname', 'tool_flexaccess')));
    $ADMIN->add('tool_flexaccess_category', new admin_externalpage(
        'tool_flexaccess_dashboard',
        get_string('dashboard', 'tool_flexaccess'),
        new moodle_url('/admin/tool/flexaccess/index.php'),
        'tool/flexaccess:viewdashboard'
    ));
    $ADMIN->add('tool_flexaccess_category', new admin_externalpage(
        'tool_flexaccess_accounts',
        get_string('accounts', 'tool_flexaccess'),
        new moodle_url('/admin/tool/flexaccess/accounts.php'),
        'tool/flexaccess:viewaccounts'
    ));
    $ADMIN->add('tool_flexaccess_category', new admin_externalpage(
        'tool_flexaccess_mailqueue',
        get_string('mailqueue', 'tool_flexaccess'),
        new moodle_url('/admin/tool/flexaccess/mailqueue.php'),
        'tool/flexaccess:managemailqueue'
    ));
    $ADMIN->add('tool_flexaccess_category', new admin_externalpage(
        'tool_flexaccess_policies',
        get_string('policies', 'tool_flexaccess'),
        new moodle_url('/admin/tool/flexaccess/policies.php'),
        'tool/flexaccess:viewpolicies'
    ));
}
