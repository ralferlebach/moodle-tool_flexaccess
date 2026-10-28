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
 * NVDA journeys N01-N13 on Moodle 5.3 (tool_flexaccess #11, COMPAT-010).
 *
 * The 13 steps of docs/accessibility-nvda.md, automated with a real NVDA on Windows (Guidepup). Every step is
 * driven by the keyboard through NVDA and asserts what NVDA actually said (see support.js for the oracle). The
 * site is a real Moodle 5.3 with the four FlexAccess plugins at the commits of the run and the fixture of
 * tests/playwright/seed.php. Only artificial fixture accounts are used; downloaded credential packages are deleted
 * unread.
 *
 * @module     tool_flexaccess/nvda/nvda-journeys.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { nvdaTest: test } = require('@guidepup/playwright');
const { expect } = require('@playwright/test');
const {
  norm, says, takeTranscript, press, perform, expectAnnounced, toWebContent, focusMain, tabThroughMain, readUntil, toMain, login,
  cli,
} = require('./support');

const E = process.env;
const COURSE = E.FLEXACCESS_COURSE_ID;
const MANAGER = [E.FLEXACCESS_MANAGER_USER, E.FLEXACCESS_MANAGER_PASS];

test.afterEach(async ({}, testInfo) => {
  await testInfo.attach('nvda-speech.txt', { body: takeTranscript(), contentType: 'text/plain' });
});

test.describe('NVDA journeys (docs/accessibility-nvda.md)', () => {
  // Fail closed: a missing fixture is an error in CI, never a skipped (green) journey.
  test.beforeAll(() => {
    for (const name of ['FLEXACCESS_COURSE_ID', 'FLEXACCESS_MANAGER_USER', 'FLEXACCESS_RECOVERY_OK_ID',
      'FLEXACCESS_RECOVERY_LOCKED_ID', 'FLEXACCESS_RECOVERY_COURSE_ID', 'FLEXACCESS_MERGE_FLEX_ID',
      'FLEXACCESS_MERGE_KEEP_ID', 'FLEXACCESS_MERGE_VISITOR_ID', 'FLEXACCESS_MERGE_COURSE_ID']) {
      if (!E[name]) {
        throw new Error(`${name} is not set: the seed step did not run.`);
      }
    }
  });

  test('N01 entry page: window title and level 1 heading are announced', async ({ page, nvda }) => {
    await page.goto(`/course/view.php?id=${COURSE}`);
    await expect(page).toHaveURL(/\/auth\/flexaccess\/access\.php/);
    await page.locator('h1').first().waitFor();
    await toWebContent(nvda, page);
    const title = await page.title();
    const saidtitle = await perform(nvda, nvda.keyboardCommands.reportTitle);
    expect(says(saidtitle, title, 3), `Title "${title}", NVDA said "${saidtitle}"`).toBe(true);
    const h1 = await page.locator('h1').first().innerText();
    let said = '';
    for (let i = 0; i < 10 && !/\blevel 1\b/.test(norm(said)); i++) {
      said = await perform(nvda, nvda.keyboardCommands.moveToNextHeading);
    }
    expect(/\bheading\b/.test(norm(said)) && /\blevel 1\b/.test(norm(said)) && says(said, h1, 3),
      `Expected "${h1}, heading, level 1", NVDA said "${said}"`).toBe(true);
    // The page's own heading follows as level 2.
    const h2 = await page.locator('#region-main h2').first().innerText();
    const saidh2 = await perform(nvda, nvda.keyboardCommands.moveToNextHeading);
    expect(/\blevel 2\b/.test(norm(saidh2)) && says(saidh2, h2, 3),
      `Expected "${h2}, heading, level 2", NVDA said "${saidh2}"`).toBe(true);
  });

  test('N02 entry page: every action is announced with name and role, in the visible order', async ({ page, nvda }) => {
    await page.goto(`/auth/flexaccess/access.php?courseid=${COURSE}`);
    await toWebContent(nvda, page);
    const seen = await tabThroughMain(nvda, page, 15);
    expect(seen.length, 'focusable actions in the main region').toBeGreaterThanOrEqual(5);
    expect(seen.some(({ node }) => node.role === 'button' && /continue/i.test(node.name))).toBe(true);
    // Shift+Tab goes back to the last action of the region and announces it again.
    const back = await press(nvda, 'Shift+Tab');
    const node = await expectAnnounced(page, back);
    expect(node.name).toBe(seen[seen.length - 1].node.name);
  });

  test('N03 quick registration: every field is announced with its label and as required', async ({ page, nvda }) => {
    await page.goto(`/auth/flexaccess/register.php?courseid=${COURSE}`);
    await toWebContent(nvda, page);
    const seen = await tabThroughMain(nvda, page, 12, (node) => node.role === 'button');
    const required = seen.filter(({ node }) => node.role === 'textbox' && String(node.required) === 'true');
    expect(required.length, 'required text fields').toBeGreaterThanOrEqual(3);
    for (const { node, said } of required) {
      expect(/\brequired\b/.test(norm(said)), `"${node.name}" is required, NVDA said "${said}"`).toBe(true);
    }
  });

  test('N04 quick registration: errors are announced at the field they belong to', async ({ page, nvda }) => {
    await page.goto(`/auth/flexaccess/register.php?courseid=${COURSE}`);
    await toWebContent(nvda, page);
    await focusMain(page);
    const first = await press(nvda, 'Tab');
    const field = await expectAnnounced(page, first);
    expect(field.role).toBe('textbox');
    await nvda.type('not-an-email-address');
    // On to the submit button and send the form from the keyboard.
    for (let i = 0; i < 10; i++) {
      const said = await press(nvda, 'Tab');
      const node = await expectAnnounced(page, said);
      if (node.role === 'button') {
        break;
      }
    }
    await press(nvda, 'Enter');
    await page.locator('#region-main [aria-invalid="true"]').first().waitFor();
    await toWebContent(nvda, page);
    const seen = await tabThroughMain(nvda, page, 10, (node) => String(node.invalid) === 'true');
    const invalid = seen[seen.length - 1];
    expect(String(invalid.node.invalid), 'an invalid field was reached').toBe('true');
    expect(invalid.node.description, 'the error message is attached to the field').not.toBe('');
    expect(/\binvalid\b/.test(norm(invalid.said)), `NVDA said "${invalid.said}"`).toBe(true);
    expect(says(invalid.said, invalid.node.description, 3),
      `Error "${invalid.node.description}" not announced; NVDA said "${invalid.said}"`).toBe(true);
  });

  test('N05 temporary access: the result is announced as text', async ({ page, nvda }) => {
    await page.goto(`/auth/flexaccess/access.php?courseid=${COURSE}`);
    await toWebContent(nvda, page);
    await focusMain(page);
    const said = await press(nvda, 'Tab');
    const node = await expectAnnounced(page, said);
    expect(node.name).toMatch(/continue/i);
    await press(nvda, 'Enter');
    await expect(page).toHaveURL(new RegExp(`/course/view\\.php\\?id=${COURSE}`));
    const notice = page.locator('.alert', { hasText: 'Temporary access granted' }).first();
    await notice.waitFor();
    const text = (await notice.innerText()).split('\n')[0];
    await toWebContent(nvda, page);
    await readUntil(nvda, [text]);
  });

  test('N06 course management is reached with the keyboard: Course > More > FlexAccess', async ({ page, nvda }) => {
    await login(page, ...MANAGER);
    // The course menu gets its keyboard handling (and moves overflowing entries into "More") once its script ran.
    await page.goto(`/course/view.php?id=${COURSE}`, { waitUntil: 'networkidle' });
    const tour = page.getByRole('button', { name: 'Skip tour' });
    if (await tour.isVisible().catch(() => false)) {
      await tour.click();
    }
    const menubar = page.locator('.secondary-navigation [role="menubar"]').first();
    await menubar.locator('[role="menuitem"]').first().focus();
    let said = '';
    let found = false;
    for (let i = 0; i < 25 && !found; i++) {
      said = await press(nvda, 'ArrowRight');
      if (says(said, 'FlexAccess', 1)) {
        found = true;
      } else if (/^more\b/.test(norm(said)) || /\bmore\b.*\b(submenu|menu|collapsed)\b/.test(norm(said))) {
        expect(/\b(submenu|menu|collapsed|expanded)\b/.test(norm(said)), `"More" is not announced as a menu: "${said}"`)
          .toBe(true);
        for (let j = 0; j < 25 && !found; j++) {
          said = await press(nvda, 'ArrowDown');
          found = says(said, 'FlexAccess', 1);
        }
      }
    }
    expect(found, `FlexAccess was not announced in the course navigation; last speech "${said}"`).toBe(true);
    await expectAnnounced(page, said);
    await press(nvda, 'Enter');
    await expect(page).toHaveURL(/\/admin\/tool\/flexaccess\/course\.php/);
  });

  test('N07 management tabs: the current tab is announced as current, Enter switches', async ({ page, nvda }) => {
    await login(page, ...MANAGER);
    await page.goto(`/admin/tool/flexaccess/course.php?courseid=${COURSE}`);
    await toWebContent(nvda, page);
    const tabs = await tabThroughMain(nvda, page, 3);
    expect(tabs.map(({ node }) => node.name)).toEqual(['Users', 'Access lists', 'Restrictions']);
    expect(tabs[0].node.current).toBe('page');
    expect(/\bcurrent\b/.test(norm(tabs[0].said)), `NVDA said "${tabs[0].said}"`).toBe(true);
    expect(/\bcurrent\b/.test(norm(tabs[1].said)), `NVDA said "${tabs[1].said}"`).toBe(false);
    // Back to "Access lists" and activate it.
    await press(nvda, 'Shift+Tab');
    await press(nvda, 'Enter');
    await expect(page).toHaveURL(/\/admin\/tool\/flexaccess\/coursebatches\.php/);
    await toWebContent(nvda, page);
    const after = await tabThroughMain(nvda, page, 2);
    expect(after[1].node.name).toBe('Access lists');
    expect(/\bcurrent\b/.test(norm(after[1].said)), `NVDA said "${after[1].said}"`).toBe(true);
  });

  test('N08 credential package: the plan and the confirmation are announced, the download leaves NVDA usable',
    async ({ page, nvda }) => {
      const batch = cli('newbatch', COURSE, 2);
      await login(page, ...MANAGER);
      await page.goto(`/admin/tool/flexaccess/batchdownload.php?id=${batch.id}`);
      const plan = (await page.locator('#region-main .alert').first().innerText()).split('\n')[0];
      expect(plan).toMatch(/\b2\b/);
      await toWebContent(nvda, page);
      await toMain(nvda);
      await readUntil(nvda, [plan]);
      const isconfirm = (node) => node.role === 'button' && /continue/i.test(node.name);
      const seen = await tabThroughMain(nvda, page, 12, isconfirm);
      expect(isconfirm(seen[seen.length - 1].node), 'the confirmation button was reached').toBe(true);
      const [download] = await Promise.all([page.waitForEvent('download'), press(nvda, 'Enter')]);
      // Never keep issued credentials: the package is deleted unread.
      await download.delete();
      const title = await perform(nvda, nvda.keyboardCommands.reportTitle);
      expect(norm(title), 'NVDA still answers after the download').not.toBe('');
    });

  test('N09 restrictions table: column headers are announced with the cells', async ({ page, nvda }) => {
    await login(page, ...MANAGER);
    const course = E.FLEXACCESS_MERGE_COURSE_ID;
    await page.goto(`/enrol/flexaccess/restrictions.php?courseid=${course}`);
    // Setup (not the subject of the journey): one rule, so the page shows its table.
    await page.getByLabel('Type', { exact: true }).selectOption('role');
    await page.getByLabel('Role', { exact: true }).selectOption({ label: 'Student' });
    await page.getByLabel('Effect', { exact: true }).selectOption('deny');
    await page.getByRole('button', { name: 'Add', exact: true }).click();
    await page.locator('#region-main table').waitFor();
    const headers = await page.locator('#region-main table th').allInnerTexts();
    await toWebContent(nvda, page);
    const table = await perform(nvda, nvda.keyboardCommands.moveToNextTable);
    expect(/\btable\b/.test(norm(table)), `NVDA said "${table}"`).toBe(true);
    await perform(nvda, nvda.keyboardCommands.moveToNextRow);
    const cell = await perform(nvda, nvda.keyboardCommands.moveToNextColumn);
    expect(says(cell, headers[1], 2) && says(cell, 'Student', 1),
      `Expected "${headers[1]}" with "Student", NVDA said "${cell}"`).toBe(true);
    const effect = await perform(nvda, nvda.keyboardCommands.moveToNextColumn);
    expect(says(effect, headers[2], 2) && says(effect, 'Deny', 1),
      `Expected "${headers[2]}" with "Deny", NVDA said "${effect}"`).toBe(true);
  });

  for (const scheme of ['light', 'dark']) {
    test(`N10${scheme === 'dark' ? '/N13' : ''} system check: every status is announced as text (${scheme})`,
      async ({ page, nvda }) => {
        await page.emulateMedia({ colorScheme: scheme });
        await login(page, ...MANAGER);
        await page.goto('/admin/tool/flexaccess/status.php');
        await expect(page.locator('html')).toHaveAttribute('data-bs-theme', scheme);
        const header = (await page.locator('#region-main table th').first().innerText()).trim();
        await toWebContent(nvda, page);
        await perform(nvda, nvda.keyboardCommands.moveToNextTable);
        await perform(nvda, nvda.keyboardCommands.moveToNextRow);
        // A column change announces the column header with the cell.
        await perform(nvda, nvda.keyboardCommands.moveToNextColumn);
        const said = await perform(nvda, nvda.keyboardCommands.moveToPreviousColumn);
        expect(says(said, header, 1) && /\b(ok|warning|error|critical)\b/.test(norm(said)),
          `Expected the column "${header}" with a status word, NVDA said "${said}"`).toBe(true);
      });

    test(`N11${scheme === 'dark' ? '/N13' : ''} merge review: fields are announced with label and state (${scheme})`,
      async ({ page, nvda }) => {
        await page.emulateMedia({ colorScheme: scheme });
        await login(page, ...MANAGER);
        const pair = scheme === 'light'
          ? [E.FLEXACCESS_MERGE_FLEX_ID, E.FLEXACCESS_MERGE_KEEP_ID]
          : [E.FLEXACCESS_MERGE_VISITOR_ID, E.FLEXACCESS_MERGE_KEEP_ID];
        await page.goto(`/admin/tool/flexaccess/ltiidentities.php?action=review&fromid=${pair[0]}&toid=${pair[1]}`);
        await expect(page.locator('html')).toHaveAttribute('data-bs-theme', scheme);
        await toWebContent(nvda, page);
        const seen = await tabThroughMain(nvda, page, 30, (node) => node.role === 'button' && /approve/i.test(node.name));
        const field = (role, name) => seen.find(({ node }) => node.role === role && name.test(node.name));
        const proof = field('combobox', /ownership proof/i);
        const evidence = field('textbox', /evidence/i);
        const confirm = field('checkbox', /verified/i);
        expect(proof && evidence && confirm, `fields announced: ${JSON.stringify(seen.map(({ node }) => node.name))}`)
          .toBeTruthy();
        // Chromium does not expose "required" for a select through every API; where it does, NVDA must say it.
        if (String(proof.node.required) === 'true') {
          expect(/\brequired\b/.test(norm(proof.said)), `NVDA said "${proof.said}"`).toBe(true);
        }
        // Nothing was chosen yet, so nothing may be announced as invalid.
        expect(/\binvalid\b/.test(norm(proof.said)), `NVDA said "${proof.said}"`).toBe(false);
        expect(/\brequired\b/.test(norm(evidence.said)), `NVDA said "${evidence.said}"`).toBe(true);
        expect(/\bnot checked\b/.test(norm(confirm.said)), `NVDA said "${confirm.said}"`).toBe(true);
        if (scheme === 'dark') {
          return;
        }
        // Submit from the keyboard; the outcome is announced, nothing changes silently.
        await page.getByLabel('Ownership proof').selectOption('reauthentication');
        await page.getByLabel('Evidence').fill('Logged in with both accounts in front of the support desk.');
        await page.getByRole('checkbox', { name: /verified/i }).check();
        await page.getByRole('button', { name: 'Approve merge' }).focus();
        await press(nvda, 'Enter');
        const notice = page.locator('.alert', { hasText: /merge/i }).first();
        await notice.waitFor();
        const text = (await notice.innerText()).split('\n')[0];
        await toWebContent(nvda, page);
        await readUntil(nvda, [text]);
      });

    test(`N12${scheme === 'dark' ? '/N13' : ''} recovery: the result or the blocker is announced (${scheme})`,
      async ({ page, nvda }) => {
        await page.emulateMedia({ colorScheme: scheme });
        await login(page, ...MANAGER);
        const user = scheme === 'light' ? E.FLEXACCESS_RECOVERY_OK_ID : E.FLEXACCESS_RECOVERY_LOCKED_ID;
        await page.goto(`/admin/tool/flexaccess/recover.php?userid=${user}&courseid=${E.FLEXACCESS_RECOVERY_COURSE_ID}`);
        await expect(page.locator('html')).toHaveAttribute('data-bs-theme', scheme);
        await toWebContent(nvda, page);
        const seen = await tabThroughMain(nvda, page, 12, (node) => node.role === 'button');
        const reactivate = seen.find(({ node }) => node.role === 'checkbox');
        if (reactivate) {
          await page.getByRole('checkbox', { name: /Reactivate/ }).focus();
          const checked = await press(nvda, 'Space');
          expect(/\bchecked\b/.test(norm(checked)) && !/\bnot checked\b/.test(norm(checked)), `NVDA said "${checked}"`)
            .toBe(true);
        }
        await page.getByRole('button', { name: 'Recover now' }).focus();
        await press(nvda, 'Enter');
        const outcome = scheme === 'light' ? 'Access restored' : 'Access not restored';
        await page.getByText(outcome).first().waitFor();
        await toWebContent(nvda, page);
        await readUntil(nvda, scheme === 'light' ? [outcome] : [outcome, 'Account suspended outside FlexAccess']);
      });
  }
});
