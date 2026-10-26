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
