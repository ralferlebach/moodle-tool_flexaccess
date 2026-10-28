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
 * Sensitivity of the NVDA speech oracle (tool_flexaccess #11, COMPAT-010): negative controls.
 *
 * An isolated fixture page (not Moodle, no product code changed) with one correct and several defective controls.
 * With real NVDA output, the oracle of the journeys must accept the correct ones and reject: a button without an
 * accessible name, an error message that is shown but not attached to its field, and a tab that cannot be reached
 * with the keyboard. If any of these passed, the journeys would prove nothing; so each rejection is asserted.
 *
 * @module     tool_flexaccess/nvda/nvda-control.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { nvdaTest: test } = require('@guidepup/playwright');
const { expect } = require('@playwright/test');
const { says, takeTranscript, press, expectAnnounced, toWebContent, focusMain } = require('./support');

test.afterEach(async ({}, testInfo) => {
  await testInfo.attach('nvda-speech.txt', { body: takeTranscript(), contentType: 'text/plain' });
});

const FIXTURE = `<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Speech oracle control</title></head>
<body>
<main id="region-main">
  <h1>Speech oracle control</h1>
  <button type="button" id="named">Save draft</button>
  <button type="button" id="unnamed"><svg aria-hidden="true" width="16" height="16"><rect width="16" height="16"/></svg></button>
  <label for="mail">Email address</label>
  <input id="mail" type="text" aria-invalid="true" aria-describedby="mailerror">
  <div id="mailerror">Enter a valid email address</div>
  <label for="fullname">Full name</label>
  <input id="fullname" type="text" aria-invalid="true">
  <div>The full name must not be empty</div>
  <ul>
    <li><a href="#first">First tab</a></li>
    <li><a class="active">Second tab</a></li>
    <li><a href="#third">Third tab</a></li>
  </ul>
</main>
</body></html>`;

test('the speech oracle accepts correct controls and rejects defective ones (real NVDA output)', async ({ page, nvda }) => {
  await page.setContent(FIXTURE);
  await toWebContent(nvda, page);
  await focusMain(page);

  // Correct: a named button is announced with name and role.
  const named = await press(nvda, 'Tab');
  await expectAnnounced(page, named);

  // Defect 1: a button without an accessible name. NVDA can only say "button"; the oracle must reject it.
  const unnamed = await press(nvda, 'Tab');
  await expect(expectAnnounced(page, unnamed)).rejects.toThrow(/no accessible name/);

  // Correct: an error message attached to its field is announced with the field.
  const mail = await press(nvda, 'Tab');
  await expectAnnounced(page, mail, { states: [/\binvalid\b/] });
  expect(says(mail, 'Enter a valid email address'), `NVDA said "${mail}"`).toBe(true);

  // Defect 2: a visible error message that is not attached. NVDA does not say it; the oracle must notice.
  const fullname = await press(nvda, 'Tab');
  await expectAnnounced(page, fullname);
  expect(says(fullname, 'The full name must not be empty'), `NVDA said "${fullname}"`).toBe(false);

  // Defect 3: a tab without a link (as Moodle renders the selected tab) is skipped by the keyboard; the check of
  // the journeys ("every tab is reached and announced") must fail for it.
  await page.locator('#fullname').focus();
  const reached = [];
  for (let i = 0; i < 3; i++) {
    const said = await press(nvda, 'Tab');
    if (!(await page.evaluate(() => !!document.activeElement.closest('main')))) {
      break;
    }
    reached.push((await expectAnnounced(page, said)).name);
  }
  expect(reached).toContain('First tab');
  expect(reached).toContain('Third tab');
  expect(() => expect(reached).toEqual(['First tab', 'Second tab', 'Third tab'])).toThrow();
});
