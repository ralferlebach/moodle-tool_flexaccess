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
 * Navigation, keyboard and colour-scheme smoke test of the FlexAccess administration (tool_flexaccess
 * #11, COMPAT-007) - the same journey on Moodle 4.5 and 5.3.
 *
 * Only roles, accessible names and URLs are used, no theme-specific DOM: the course entry is found
 * through the course's secondary navigation (directly or behind "More"), tabs through their link
 * names. Every page must have a level-1 page heading and a level-2 section heading, every tab must be
 * reachable with the keyboard, and the pages must pass axe's colour-contrast rule in the light and in
 * the dark colour scheme.
 *
 * @module     tool_flexaccess/navigation.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { test, expect } = require('@playwright/test');
const { loginAs } = require('./helpers');
const AxeBuilder = require('@axe-core/playwright').default;

const COURSE_ID = process.env.FLEXACCESS_COURSE_ID;
const MANAGER_USER = process.env.FLEXACCESS_MANAGER_USER;
const MANAGER_PASS = process.env.FLEXACCESS_MANAGER_PASS;

/**
 * Serious or critical colour-contrast violations of the page's main region.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @returns {Promise<Array>} Violations.
 */
async function contrastViolations(page) {
  const results = await new AxeBuilder({ page }).include('#region-main').withRules(['color-contrast']).analyze();
  return results.violations.filter((v) => ['serious', 'critical'].includes(v.impact));
}

/**
 * Assert the page structure every FlexAccess page shares.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @returns {Promise<void>}
 */
async function expectHeadings(page) {
  await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
  await expect(page.locator('#region-main').getByRole('heading', { level: 2 }).first()).toBeVisible();
}

test.describe('FlexAccess navigation on the supported Moodle versions', () => {
  test.skip(!COURSE_ID || !MANAGER_USER || !MANAGER_PASS, 'Manager credentials were not seeded.');

  test.beforeEach(async ({ page }) => {
    await loginAs(page, MANAGER_USER, MANAGER_PASS);
    await page.waitForLoadState('domcontentloaded');
  });

  test('Course > More > FlexAccess opens the course management with keyboard-reachable tabs', async ({ page }) => {
    await page.goto(`/course/view.php?id=${COURSE_ID}`, { waitUntil: 'networkidle' });
    // A user tour may open over the course page on a fresh site.
    const skip = page.getByRole('button', { name: 'Skip tour' });
    if (await skip.isVisible()) {
      await skip.click();
    }
    // The course's secondary navigation: the navigation that offers "Participants" (link on 4.5,
    // menu item in the menu bar on 5.x).
    const entryIn = (scope) => scope.getByRole('menuitem', { name: 'FlexAccess', exact: true })
      .or(scope.getByRole('link', { name: 'FlexAccess', exact: true }));
    const secondary = page.getByRole('navigation')
      .filter({ has: page.getByRole('menuitem', { name: 'Participants' }).or(page.getByRole('link', { name: 'Participants' })) })
      .last();
    let entry = entryIn(secondary).first();
    if (!(await entry.isVisible())) {
      // Entries that do not fit move into "More".
      await secondary.getByRole('menuitem', { name: /More/ }).or(secondary.getByRole('button', { name: /More/ })).first().click();
      entry = entryIn(page).first();
    }
    await entry.click();
    await expect(page).toHaveURL(/\/admin\/tool\/flexaccess\//);
    await expectHeadings(page);

    // The selected tab ("Users") is the current page and not a link; the others are links.
    await expect(page.locator('#region-main').getByText('Users', { exact: true }).first()).toBeVisible();
    for (const name of ['Access lists', 'Restrictions']) {
      const tab = page.locator('#region-main').getByRole('link', { name, exact: true }).first();
      await expect(tab).toBeVisible();
      await tab.focus();
      await expect(tab).toBeFocused();
    }
    // Keyboard activation of a tab, not a mouse click.
    await page.locator('#region-main').getByRole('link', { name: 'Restrictions', exact: true }).first().press('Enter');
    await expect(page).toHaveURL(/restrictions\.php/);
    await expectHeadings(page);
  });

  for (const scheme of ['light', 'dark']) {
    test(`site administration pages keep their contrast in the ${scheme} colour scheme`, async ({ page }) => {
      await page.emulateMedia({ colorScheme: scheme });
      const pages = [
        '/admin/tool/flexaccess/index.php',
        '/admin/tool/flexaccess/accounts.php',
        '/admin/tool/flexaccess/status.php',
        '/admin/tool/flexaccess/ltiidentities.php',
        `/admin/tool/flexaccess/course.php?courseid=${COURSE_ID}`,
      ];
      for (const url of pages) {
        const response = await page.goto(url, { waitUntil: 'networkidle' });
        expect(response.status(), url).toBe(200);
        await expectHeadings(page);
        // Moodle 5.3+ (theme_boost colour modes, enabled by the seed) applies the scheme as data-bs-theme;
        // without colour-mode support (4.5) there is no dark mode to check.
        const applied = await page.locator('html').getAttribute('data-bs-theme');
        if (applied === null) {
          test.skip(scheme === 'dark', 'This Moodle version has no colour modes (dark mode starts with 5.3).');
        } else {
          expect(applied, url).toBe(scheme);
        }
        const violations = await contrastViolations(page);
        expect(violations, `${url}: ${JSON.stringify(violations.map((v) => v.nodes.map((n) => n.target)), null, 2)}`)
          .toEqual([]);
      }
    });
  }

  test('the system tabs are reachable with the keyboard and lead to the LTI identities', async ({ page }) => {
    await page.goto('/admin/tool/flexaccess/status.php');
    const tab = page.locator('#region-main').getByRole('link', { name: 'LTI identities', exact: true }).first();
    await expect(tab).toBeVisible();
    await tab.focus();
    await expect(tab).toBeFocused();
    await tab.press('Enter');
    await expect(page).toHaveURL(/ltiidentities\.php/);
    await expectHeadings(page);
  });
});
