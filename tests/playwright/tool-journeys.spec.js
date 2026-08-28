/*
 * This file is part of Moodle - https://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * What tool_flexaccess is responsible for, exercised in a browser: the operator's pages - account
 * overview, mail queue, policies, invitations, campaigns and anonymous access lists.
 */

const { test, expect } = require('@playwright/test');
const { loginAs, fillPasswordUnmask, open, submitForm, chooseCourse } = require('./helpers');

const ADMIN_USER = process.env.FLEXACCESS_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.FLEXACCESS_ADMIN_PASS || 'Admin!23';
const COURSE_ID = process.env.FLEXACCESS_COURSE_ID;
const COURSE_NAME = process.env.FLEXACCESS_COURSE_NAME || 'My favourite course';

/**
 * Build a readable address that stays unique across retries.
 *
 * A retry would otherwise reuse an address that the first attempt already registered. The first
 * attempt - the one whose screenshots are used as illustrations - keeps the plain name.
 *
 * @param {string} local The local part, for example 'john.doe'.
 * @param {import('@playwright/test').TestInfo} testInfo The current test info.
 * @returns {string}
 */
function personEmail(local, testInfo) {
  return testInfo.retry ? `${local}.${testInfo.retry}@example.org` : `${local}@example.org`;
}

test.describe('FlexAccess administration', () => {
  test.beforeEach(async ({ page }) => {
    // These pages require moodle/site:config, so they need the site administrator - not the
    // manager account the seed creates for the accessibility checks.
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await expect(
      page,
      `Login as "${ADMIN_USER}" failed; these pages need the site administrator.`
    ).not.toHaveURL(/\/login\//);
  });

  test('the dashboard and account list render', async ({ page }) => {
    await open(page, '/admin/tool/flexaccess/index.php');
    await open(page, '/admin/tool/flexaccess/accounts.php');
    await expect(page.locator('body')).toContainText(/account/i);
  });

  test('the mail queue renders', async ({ page }) => {
    await open(page, '/admin/tool/flexaccess/mailqueue.php');
    await expect(page.locator('body')).toContainText(/queue|warteschlange/i);
  });

  test('the site and category policies render', async ({ page }) => {
    await open(page, '/admin/tool/flexaccess/policies.php');
    await expect(page.locator('body')).toContainText(/polic|richtlinie/i);
  });

  test('an invitation can be created and appears in the list', async ({ page }, testInfo) => {
    test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
    await open(page, '/admin/tool/flexaccess/invitations.php?action=new');
    const address = testInfo.retry ? `john.doe.${testInfo.retry}@example.org` : 'john.doe@example.org';
    await chooseCourse(page, COURSE_ID, COURSE_NAME);
    await page.locator('#id_emails, textarea[name="emails"], input[name="emails"]').first().fill(address);
    await submitForm(page);
    await expect(page.locator('body')).toContainText(address);
  });

  test('an access list can be created for a course', async ({ page }, testInfo) => {
    test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
    const name = testInfo.retry ? `My first list (${testInfo.retry})` : 'My first list';
    await open(page, `/admin/tool/flexaccess/coursebatches.php?courseid=${COURSE_ID}&action=new`);
    await page.locator('#id_name, input[name="name"]').first().fill(name);
    await page.locator('#id_count, input[name="count"]').first().fill('2');
    await submitForm(page);
    await expect(page.locator('body')).toContainText(name);
  });

  test('a campaign link is shown exactly once on creation', async ({ page }, testInfo) => {
    test.skip(!COURSE_ID, 'FLEXACCESS_COURSE_ID not provided by the seed step');
    const name = testInfo.retry ? `Open day 2026 (${testInfo.retry})` : 'Open day 2026';
    await open(page, '/admin/tool/flexaccess/campaigns.php?action=new');
    await page.locator('#id_name, input[name="name"]').first().fill(name);
    await chooseCourse(page, COURSE_ID, COURSE_NAME);
    await submitForm(page);

    // The plaintext link is stored hashed and must appear on this response only.
    await expect(page.locator('body')).toContainText(/campaign\.php\?token=/);
    await open(page, '/admin/tool/flexaccess/campaigns.php');
    await expect(page.locator('body')).not.toContainText(/campaign\.php\?token=/);
  });

  test('the batch list is reachable', async ({ page }) => {
    await open(page, '/admin/tool/flexaccess/batches.php');
    await expect(page.locator('body')).toContainText(/batch|zugangsliste/i);
  });
});
