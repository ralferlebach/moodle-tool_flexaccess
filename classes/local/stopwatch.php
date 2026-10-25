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
 * Monotonic, high-resolution measurement of elapsed run time (issue #13).
 *
 * Run-time budgets (for example the reconciliation's time limit) measure how long the process has
 * been working, not what time it is. They therefore must not read \core\clock: a frozen clock in a
 * test - or a wall clock that jumps (NTP, DST misconfiguration) - would make a budget never expire or
 * expire at once. hrtime() is monotonic and nanosecond-resolved. The class is resolved through
 * \core\di so that a test can inject a deterministic stopwatch.
 *
 * Business time (deadlines, windows, stored timestamps) always comes from \core\clock.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stopwatch {
    /**
     * A start mark.
     *
     * @return int Opaque monotonic mark (nanoseconds).
     */
    public function start(): int {
        return hrtime(true); // Monotonic duration, not a point in time (clock-exception).
    }

    /**
     * Seconds elapsed since a start mark.
     *
     * @param int $mark Mark returned by {@see self::start()}.
     * @return float Elapsed seconds (never negative).
     */
    public function seconds_since(int $mark): float {
        return max(0, hrtime(true) - $mark) / 1e9; // Monotonic duration (clock-exception).
    }

    /**
     * Whether a budget is still open.
     *
     * @param int $mark Start mark.
     * @param int $budget Budget in seconds; 0 means unlimited.
     * @return bool
     */
    public function within(int $mark, int $budget): bool {
        return $budget === 0 || $this->seconds_since($mark) < $budget;
    }

    /**
     * The stopwatch in use (replaceable in tests through \core\di::set()).
     *
     * @return self
     */
    public static function instance(): self {
        return \core\di::get(self::class);
    }
}
