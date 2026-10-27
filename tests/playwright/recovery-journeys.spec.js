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
 * Recovery journeys (tool_flexaccess #10): a recovery reports "access restored" only when the person
 * really gets back into the course, and names what still blocks access otherwise.
 */

const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { loginAs, open } = require('./helpers');

const ADMIN_USER = process.env.FLEXACCESS_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.FLEXACCESS_ADMIN_PASS || 'Admin!23';
const COURSE = process.env.FLEXACCESS_RECOVERY_COURSE_ID;
const OK_ID = process.env.FLEXACCESS_RECOVERY_OK_ID;
const LOCKED_ID = process.env.FLEXACCESS_RECOVERY_LOCKED_ID;
const CLOSED_ID = process.env.FLEXACCESS_RECOVERY_CLOSED_ID;
const PASS = process.env.FLEXACCESS_RECOVERY_PASS;

test.describe('FlexAccess recovery postcondition', () => {
  test.skip(!COURSE || !OK_ID, 'Recovery fixtures not provided by the seed step');

  test('suspended access: preview, confirm, and the course opens at once', async ({ page, browser }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await open(page, `/admin/tool/flexaccess/recover.php?userid=${OK_ID}&courseid=${COURSE}`);
    const preview = page.locator('table');
    // The preview shows what Core will decide on: method, role, time window and the expected result.
    await expect(preview).toContainText('enrolment method enabled');
    await expect(preview).toContainText('course role present');
    await expect(preview).toContainText('Moodle user suspended: No');
    await expect(preview).toContainText(/Expected afterwards: course access effective if selected/);
    await page.getByRole('checkbox', { name: /Reactivate/ }).check();
    await page.getByRole('button', { name: 'Recover now' }).click();
    await expect(page.locator('body')).toContainText('Access restored: account and course access are effective');
    await expect(page.locator('body')).toContainText('Course access reactivated');

    // The person signs in and the course opens directly - no enrolment page, no waiting.
    const context = await browser.newContext();
    const learner = await context.newPage();
    await loginAs(learner, 'flexrecoverok', PASS);
    await expect(learner).not.toHaveURL(/\/login\//);
    const response = await learner.goto(`/course/view.php?id=${COURSE}`);
    expect(response.status()).toBe(200);
    await expect(learner).toHaveURL(new RegExp(`/course/view\\.php\\?id=${COURSE}`));
    await expect(learner.locator('body')).toContainText('Recovery course');
    await context.close();
  });

  test('administrator lock: no misleading success', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await open(page, `/admin/tool/flexaccess/recover.php?userid=${LOCKED_ID}&courseid=${COURSE}`);
    await expect(page.locator('table')).toContainText('Moodle user suspended: yes, by an administrator');
    await expect(page.locator('table')).toContainText('FlexAccess does not lift that suspension');
    const reactivate = page.getByRole('checkbox', { name: /Reactivate/ });
    if (await reactivate.count()) {
      await reactivate.check();
    }
    await page.getByRole('button', { name: 'Recover now' }).click();
    const body = page.locator('body');
    await expect(body).toContainText('Access not restored');
    await expect(body).toContainText('Account suspended outside FlexAccess: nothing changed');
    await expect(body).toContainText('The Moodle user is suspended');
    await expect(body).not.toContainText('Access restored:');
    await expect(body).not.toContainText('Course access reactivated');
  });

  test('disabled enrolment method: a clear blocker instead of success', async ({ page }) => {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
    await open(page, `/admin/tool/flexaccess/recover.php?userid=${CLOSED_ID}`);
    const preview = page.locator('table');
    await expect(preview).toContainText('enrolment method disabled');
    // Nothing to tick: reactivating would be refused, so the preview points at the fix instead.
    await expect(page.getByRole('checkbox', { name: /Reactivate/ })).toHaveCount(0);
    await expect(preview.getByRole('link', { name: 'Resolve' }).first()).toHaveAttribute('href', /\/enrol\/instances\.php\?id=/);
    await page.getByRole('button', { name: 'Recover now' }).click();
    const body = page.locator('body');
    await expect(body).toContainText('Access not restored');
    await expect(body).toContainText(/FAPWCLOSED[^:]*: FlexAccess enrolment method disabled/);
    await expect(body.getByRole('link', { name: 'Resolve' }).first()).toHaveAttribute('href', /\/enrol\/instances\.php\?id=/);
    await expect(body).not.toContainText('Access restored:');
  });

  test('access expired and suspended, previewed, recovered: course and active-only lists at once', async ({ page, browser }) => {
    // tool #10 / enrol #9: the whole path, starting from an active enrolment. FlexAccess enrolments are suspended by
    // their lifecycle (end of the access window, expiry task) - Moodle's "Edit enrolment" page is not offered for them.
    const cli = (...args) => JSON.parse(execFileSync('php', [path.join(__dirname, 'merge_cli.php'), ...args.map(String)],
      { encoding: 'utf8' }).trim().split('\n').pop());
    const person = cli('activeue', COURSE);
    expect(person.status).toBe(0);
    await loginAs(page, ADMIN_USER, ADMIN_PASS);

    // Suspend: the access window ends and the expiry task runs.
    expect(cli('expireue', person.ueid).status).toBe(1);
    expect(cli('uestatus', person.ueid)).toEqual({ status: 1, activeparticipant: false, inactivelist: false });
    // The person cannot open the course any more.
    const before = await (await browser.newContext()).newPage();
    await loginAs(before, person.username, person.password);
    await before.goto(`/course/view.php?id=${COURSE}`);
    await expect(before).not.toHaveURL(new RegExp(`/course/view\\.php\\?id=${COURSE}$`));
    // Active-only lists (grader report with Moodle's default "show only active enrolments") no longer show the person.
    await open(page, `/grade/report/grader/index.php?id=${COURSE}`);
    await expect(page.locator('body')).not.toContainText(person.fullname);

    // Preview, then recover.
    await open(page, `/admin/tool/flexaccess/recover.php?userid=${person.id}&courseid=${COURSE}`);
    await expect(page.locator('table')).toContainText(/Expected afterwards: course access effective if selected/);
    await page.getByRole('checkbox', { name: /Reactivate/ }).check();
    await page.getByRole('button', { name: 'Recover now' }).click();
    await expect(page.locator('body')).toContainText('Access restored: account and course access are effective');

    // At once, without cron: Core counts the person as active, the active-only list shows them ...
    expect(cli('uestatus', person.ueid)).toEqual({ status: 0, activeparticipant: true, inactivelist: true });
    await open(page, `/grade/report/grader/index.php?id=${COURSE}`);
    await expect(page.locator('body')).toContainText(person.fullname);
    // ... and the person opens the course directly.
    const context = await browser.newContext();
    const learner = await context.newPage();
    await loginAs(learner, person.username, person.password);
    const response = await learner.goto(`/course/view.php?id=${COURSE}`);
    expect(response.status()).toBe(200);
    await expect(learner).toHaveURL(new RegExp(`/course/view\\.php\\?id=${COURSE}`));
    await context.close();
  });
});
