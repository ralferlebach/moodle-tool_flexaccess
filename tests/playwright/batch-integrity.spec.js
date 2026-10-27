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
 * Credential package and import integrity at the real entry points.
 *
 * tool_flexaccess #15: the confirmation names what will be issued; GET changes nothing; the confirmed POST
 * delivers one complete package and changes every password once; replaying the same POST is refused with
 * nothing changed.
 * tool_flexaccess #15 (interrupted delivery): the network transfer breaks after the server has committed the issue.
 * HTTP cannot prove that a browser received a file, so the guarantee is: the server side is atomic, the lost
 * package cannot be replayed or silently re-rotated, and a person with the site right performs an audited
 * reissue that invalidates the lost passwords; no plain password is stored anywhere.
 * tool_flexaccess #16: an import with a record outside the read window is refused on the form, and the
 * accounts stay exactly as they were.
 *
 * @module     tool_flexaccess/batch-integrity.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { loginAs } = require('./helpers');

const E = process.env;

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

test.describe('Batch credential and import integrity (tool_flexaccess #15, #16)', () => {
  test.skip(!E.FLEXACCESS_MANAGER_USER || !E.FLEXACCESS_COURSE_ID, 'The fixture was not seeded.');

  test('issuing: named plan, GET changes nothing, one complete package, a replay is refused', async ({ page }) => {
    const batch = cli('newbatch', E.FLEXACCESS_COURSE_ID, 3);
    const before = cli('batchstate', batch.id);
    await loginAs(page, E.FLEXACCESS_MANAGER_USER, E.FLEXACCESS_MANAGER_PASS);
    const url = `/admin/tool/flexaccess/batchdownload.php?id=${batch.id}`;
    await page.goto(url);
    await expect(page.locator('body')).toContainText(/\b3\b/);
    const form = page.locator('form:has(input[name="token"])');
    await expect(form).toHaveAttribute('method', /post/i);
    const token = await form.locator('input[name="token"]').inputValue();
    const sesskey = await page.evaluate(() => window.M.cfg.sesskey);

    // GET with every parameter of the confirmation changes nothing.
    await page.request.get(`${url}&confirm=1&token=${token}&sesskey=${sesskey}`);
    expect(cli('batchstate', batch.id)).toEqual(before);

    // The confirmed POST delivers the package.
    const [download] = await Promise.all([page.waitForEvent('download'), form.getByRole('button').click()]);
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'issue-'));
    const zip = path.join(dir, download.suggestedFilename());
    await download.saveAs(zip);
    const listing = execFileSync('unzip', ['-Z1', zip], { encoding: 'utf8' }).trim().split('\n').sort();
    expect(listing).toHaveLength(3);
    expect(listing.filter((n) => /\.xlsx$/.test(n))).toHaveLength(1);
    expect(listing.filter((n) => /\.pdf$/.test(n))).toHaveLength(2);
    for (const pdf of listing.filter((n) => /\.pdf$/.test(n))) {
      execFileSync('unzip', ['-o', '-q', zip, pdf, '-d', dir]);
      const text = execFileSync('pdftotext', [path.join(dir, pdf), '-'], { encoding: 'utf8' });
      for (const username of batch.usernames) {
        expect(text, pdf).toContain(username);
      }
    }
    const issued = cli('batchstate', batch.id);
    expect(issued.timeissued).toBeGreaterThan(0);
    expect(issued.issued).toBe(before.issued + 1);
    issued.users.forEach((u, i) => expect(u[1]).not.toBe(before.users[i][1]));

    // Replaying the same confirmation (double click, reload, resubmission) is refused.
    const replay = await page.request.post(url, { form: { id: batch.id, confirm: 1, token, sesskey } });
    expect(replay.headers()['content-type']).not.toContain('zip');
    expect(await replay.text()).toMatch(/changed since you confirmed|seit Ihrer Bestätigung/);
    expect(cli('batchstate', batch.id)).toEqual(issued);
  });

  test('interrupted delivery after the server committed: no replay, no silent rotation, audited reissue', async ({
    page, browser,
  }) => {
    const batch = cli('newbatch', E.FLEXACCESS_COURSE_ID, 3);
    const before = cli('batchstate', batch.id);
    const issuer = cli('courseissuer', E.FLEXACCESS_COURSE_ID);
    const url = `/admin/tool/flexaccess/batchdownload.php?id=${batch.id}`;
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'interrupted-'));

    // 1. A course manager (may issue once, may not reissue) confirms; the server builds, checks and commits the
    //    package, and the connection breaks before the browser receives it.
    await loginAs(page, issuer.username, issuer.password);
    await page.goto(url);
    const form = page.locator('form:has(input[name="token"])');
    const token = await form.locator('input[name="token"]').inputValue();
    const sesskey = await page.evaluate(() => window.M.cfg.sesskey);
    const lost = path.join(dir, 'lost.zip');
    let served = 0;
    await page.route(/batchdownload\.php/, async (route) => {
      if (route.request().method() !== 'POST') {
        return route.continue();
      }
      const response = await route.fetch();
      served = response.status();
      fs.writeFileSync(lost, await response.body());
      await route.abort('connectionreset');
    });
    let downloaded = false;
    page.on('download', () => {
      downloaded = true;
    });
    await form.getByRole('button').click().catch(() => null);
    await expect.poll(() => served).toBe(200);
    await page.unroute(/batchdownload\.php/);
    expect(downloaded).toBe(false);

    // The server committed: every password changed once and the issue is audited once.
    const issued = cli('batchstate', batch.id);
    expect(issued.timeissued).toBeGreaterThan(0);
    expect(issued.issued).toBe(before.issued + 1);
    issued.users.forEach((u, i) => expect(u[1]).not.toBe(before.users[i][1]));
    const lostcreds = cli('packagecreds', lost);
    expect(Object.keys(lostcreds).sort()).toEqual([...batch.usernames].sort());

    // 2. Retrying the same submission does not hand out a second package and rotates nothing.
    const replay = await page.request.post(url, { form: { id: batch.id, confirm: 1, token, sesskey } });
    expect(replay.headers()['content-type']).not.toContain('zip');
    expect(cli('batchstate', batch.id)).toEqual(issued);

    // 3. The course manager is told that only a site administrator can reissue; nothing changes.
    await page.goto(url);
    await expect(page.locator('body')).toContainText(/already been issued|bereits ausgestellt/);
    await expect(page.locator('form:has(input[name="token"])')).toHaveCount(0);
    expect(cli('batchstate', batch.id)).toEqual(issued);
    for (const [username, password] of Object.entries(lostcreds)) {
      expect(cli('checkpw', username, password).valid, username).toBe(true);
    }

    // 4. The manager with the site right reissues: confirmation, complete package, new audited issue.
    const context = await browser.newContext();
    const manager = await context.newPage();
    await loginAs(manager, E.FLEXACCESS_MANAGER_USER, E.FLEXACCESS_MANAGER_PASS);
    await manager.goto(url);
    const reform = manager.locator('form:has(input[name="token"])');
    await expect(reform).toHaveAttribute('method', /post/i);
    const [download] = await Promise.all([manager.waitForEvent('download'), reform.getByRole('button').click()]);
    const fresh = path.join(dir, download.suggestedFilename());
    await download.saveAs(fresh);
    await context.close();
    const freshcreds = cli('packagecreds', fresh);
    expect(Object.keys(freshcreds).sort()).toEqual([...batch.usernames].sort());
    const reissued = cli('batchstate', batch.id);
    expect(reissued.issued).toBe(before.issued + 2);
    const events = cli('issueevents', batch.id);
    expect(events.map((e) => e.reissue)).toEqual([false, true]);
    expect(events.every((e) => e.count === batch.usernames.length)).toBe(true);
    expect(events[0].userid).not.toBe(events[1].userid);

    // The lost passwords no longer sign anyone in; the reissued ones do.
    for (const username of batch.usernames) {
      expect(freshcreds[username]).not.toBe(lostcreds[username]);
      expect(cli('checkpw', username, lostcreds[username]).valid, username).toBe(false);
      expect(cli('checkpw', username, freshcreds[username]).valid, username).toBe(true);
    }

    // 5. No plain password of either package is stored: events, configuration, tasks, batch and user records.
    const scan = cli('plaintext', batch.id, JSON.stringify([...Object.values(lostcreds), ...Object.values(freshcreds)]));
    expect(scan.checked).toBe(2 * batch.usernames.length);
    expect(scan.found).toEqual([]);
  });

  test('import: a record outside the read window is refused on the form, nothing changes', async ({ page }) => {
    const batch = cli('newbatch', E.FLEXACCESS_COURSE_ID, 2);
    const fixture = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'import-')), 'list.xlsx');
    cli('importfixture', batch.id, fixture, 50000);
    const before = cli('batchstate', batch.id);

    await loginAs(page, E.FLEXACCESS_MANAGER_USER, E.FLEXACCESS_MANAGER_PASS);
    await page.goto(`/admin/tool/flexaccess/batchconvert.php?id=${batch.id}`);
    await page.locator('.fp-btn-choose, input[name="excelfilechoose"]').first().click();
    const dialog = page.locator('.moodle-dialogue-base:visible, .filepicker:visible').first();
    await dialog.getByText(/Upload a file|Datei hochladen/).first().click();
    await dialog.locator('input[type="file"][name="repo_upload_file"]').setInputFiles(fixture);
    await dialog.locator('.fp-upload-btn').first().click();
    await expect(page.locator('.filepicker-filename')).toContainText('list.xlsx');
    await page.locator('#id_submitbutton').click();

    await expect(page.locator('.alert-danger, .alert-error')).toContainText(/50000/);
    await expect(page.locator('form input[name="excelfile"]')).toHaveCount(1);
    expect(cli('batchstate', batch.id)).toEqual(before);
  });
});
