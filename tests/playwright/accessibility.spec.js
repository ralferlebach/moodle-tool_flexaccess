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
 * Accessibility gate for the anonymous-facing FlexAccess pages.
 *
 * These pages (the access entry point and the quick-registration form) are shown to visitors who are
 * not logged in, so they carry the highest accessibility obligation. We run the axe-core WCAG 2.1 A
 * and AA rule sets and fail the build on any serious or critical violation.
 *
 * @module     enrol_flexaccess/accessibility.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { loginAs } = require('./helpers');
const AxeBuilder = require('@axe-core/playwright').default;

const COURSE_ID = process.env.FLEXACCESS_COURSE_ID;
const MANAGER_USER = process.env.FLEXACCESS_MANAGER_USER;
const MANAGER_PASS = process.env.FLEXACCESS_MANAGER_PASS;

const BLOCKING_IMPACTS = ['serious', 'critical'];

/**
 * Run axe against the current page and return only the blocking violations.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @returns {Promise<Array>} The serious/critical violations.
 */
async function blockingViolations(page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();
    return results.violations.filter((v) => BLOCKING_IMPACTS.includes(v.impact));
}

/**
 * Open a page and assert it really rendered (HTTP 200 with the expected heading), so axe can never
 * pass by analysing a 404 or login redirect instead of the page under test.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @param {string} url The URL to open.
 * @param {RegExp} heading Expected visible heading on the rendered page.
 * @returns {Promise<void>}
 */
async function openAndVerify(page, url, heading) {
    const response = await page.goto(url);
    expect(response, `No response for ${url}`).not.toBeNull();
    expect(response.status(), `Unexpected HTTP status for ${url}`).toBe(200);
    // .first(): the page legitimately carries several headings matching the pattern (site, course
    // and section). Any of them proves the page rendered, so a strict single match is the wrong
    // assertion here - it failed as "resolved to 3 elements".
    await expect(page.getByRole('heading', { name: heading }).first()).toBeVisible();
}



/**
 * Log in through the standard Moodle login form so the administrative pages can be reached.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @returns {Promise<void>}
 */
async function loginAsManager(page) {
  await loginAs(page, MANAGER_USER, MANAGER_PASS);
  await page.waitForLoadState('domcontentloaded');
}

test.describe('FlexAccess administrative pages accessibility', () => {
    // Skip (never fail) when the seed did not provide credentials, so the gate cannot go red for
    // reasons unrelated to accessibility.
    test.skip(!COURSE_ID || !MANAGER_USER || !MANAGER_PASS, 'Manager credentials were not seeded.');

    test.beforeEach(async ({ page }) => {
        await loginAsManager(page);
    });

    const adminPages = [
        ['batch list', '/admin/tool/flexaccess/batches.php'],
        ['invitation list', '/admin/tool/flexaccess/invitations.php'],
        ['campaign list', '/admin/tool/flexaccess/campaigns.php'],
        ['course access lists', `/admin/tool/flexaccess/coursebatches.php?courseid=${COURSE_ID}`],
        ['course restrictions', `/enrol/flexaccess/restrictions.php?courseid=${COURSE_ID}`],
    ];

    for (const [label, url] of adminPages) {
        test(`${label} has no serious accessibility violations`, async ({ page }) => {
            const response = await page.goto(url);
            expect(response, `No response for ${url}`).not.toBeNull();
            expect(response.status(), `Unexpected HTTP status for ${url}`).toBe(200);
            const violations = await blockingViolations(page);
            expect(violations, JSON.stringify(violations.map((v) => v.id), null, 2)).toEqual([]);
        });
    }
});

/**
 * Run the CLI helper on the site under test.
 *
 * @param {...(string|number)} args Command and arguments.
 * @returns {object} Parsed JSON output.
 */
function cli(...args) {
    const file = path.join(__dirname, 'merge_cli.php');
    return JSON.parse(execFileSync('php', [file, ...args.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
}

// tool_flexaccess #11 (Moodle 5.3, accessibility): every FlexAccess administration page, including forms,
// confirmations and the merge review, in the light colour scheme and - where Moodle has colour modes (5.3+) - in
// the dark one. Full WCAG 2.1 A/AA rule set, not only contrast. The manager follows the browser's colour scheme
// (seed), so the emulated scheme is the one Moodle renders.
test.describe('Every FlexAccess administration page, light and dark (tool_flexaccess #11)', () => {
    test.skip(!COURSE_ID || !MANAGER_USER || !MANAGER_PASS || !process.env.FLEXACCESS_RECOVERY_OK_ID,
        'The fixture was not seeded.');

    for (const scheme of ['light', 'dark']) {
        test(`no serious WCAG 2.1 AA violation on any page in the ${scheme} colour scheme`, async ({ page }) => {
            // 18 pages with a full axe run each.
            test.setTimeout(300000);
            const E = process.env;
            const batch = cli('newbatch', COURSE_ID, 2);
            const pages = [
                '/admin/tool/flexaccess/index.php',
                '/admin/tool/flexaccess/accounts.php',
                '/admin/tool/flexaccess/status.php',
                '/admin/tool/flexaccess/ltiidentities.php',
                `/admin/tool/flexaccess/ltiidentities.php?action=review&fromid=${E.FLEXACCESS_MERGE_FLEX_ID}`
                    + `&toid=${E.FLEXACCESS_MERGE_KEEP_ID}`,
                '/admin/tool/flexaccess/lockreview.php',
                '/admin/tool/flexaccess/mailqueue.php',
                `/admin/tool/flexaccess/course.php?courseid=${COURSE_ID}`,
                `/admin/tool/flexaccess/coursebatches.php?courseid=${COURSE_ID}`,
                '/admin/tool/flexaccess/batches.php',
                `/admin/tool/flexaccess/batchdownload.php?id=${batch.id}`,
                `/admin/tool/flexaccess/batchconvert.php?id=${batch.id}`,
                '/admin/tool/flexaccess/invitations.php',
                '/admin/tool/flexaccess/campaigns.php',
                `/admin/tool/flexaccess/user.php?userid=${E.FLEXACCESS_RECOVERY_OK_ID}`,
                `/admin/tool/flexaccess/recover.php?userid=${E.FLEXACCESS_RECOVERY_OK_ID}`
                    + `&courseid=${E.FLEXACCESS_RECOVERY_COURSE_ID}`,
                `/enrol/flexaccess/restrictions.php?courseid=${COURSE_ID}`,
            ];
            await page.emulateMedia({ colorScheme: scheme });
            await loginAsManager(page);
            const found = {};
            for (const url of pages) {
                const response = await page.goto(url);
                expect(response.status(), url).toBe(200);
                await page.waitForLoadState('load');
                await expect(page.getByRole('heading', { level: 1 }).first(), url).toBeVisible();
                const applied = await page.locator('html').getAttribute('data-bs-theme');
                if (applied === null) {
                    test.skip(scheme === 'dark', 'This Moodle version has no colour modes (dark mode starts with 5.3).');
                } else {
                    expect(applied, url).toBe(scheme);
                }
                const violations = await blockingViolations(page);
                if (violations.length) {
                    found[url] = violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
                }
            }
            expect(found).toEqual({});
        });
    }
});

