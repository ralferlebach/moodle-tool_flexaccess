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

namespace tool_flexaccess;

/**
 * The invitation and campaign pages create sessions only through the auth login guard.
 *
 * The auth plugin checks its own code; this plugin checks its own, so neither CI depends on the
 * version of the other that happens to be installed.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class session_creation_test extends \advanced_testcase {
    /**
     * No direct complete_user_login() anywhere in this plugin.
     *
     * @return void
     */
    public function test_no_direct_session_creation(): void {
        $root = \core_component::get_component_directory('tool_flexaccess');
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = $file->getPathname();
            if (substr($path, -4) !== '.php' || strpos($path, '/tests/') !== false || strpos($path, '/vendor/') !== false) {
                continue;
            }
            // Strip comments so a mention in documentation is not mistaken for a call.
            $code = '';
            foreach (token_get_all((string) file_get_contents($path)) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= is_array($token) ? $token[1] : $token;
            }
            if (preg_match('/\bcomplete_user_login\s*\(/', $code)) {
                $offenders[] = substr($path, strlen($root));
            }
        }
        $this->assertSame([], $offenders, 'Direct complete_user_login() instead of \auth_flexaccess\api::complete_login().');
    }
}
