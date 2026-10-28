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
 * Speech oracle of the NVDA journeys (tool_flexaccess #11, COMPAT-010).
 *
 * What NVDA says is compared with what the browser exposes to it: the accessible name, role and states of the
 * focused element as Chromium computes them (DevTools protocol, the same tree NVDA reads through UI Automation /
 * IAccessible2). A journey therefore passes only when NVDA really announced the element - not when the DOM merely
 * looks right - and fails when the element has no name, when NVDA did not speak it, or when a required state
 * (required, invalid, checked, current) is missing from the speech.
 *
 * Every assertion clears the speech log first and reads only what the one NVDA command it wraps produced, so an
 * announcement of an earlier step can never satisfy a later one. Every failure message carries the expected
 * facts, the speech actually captured and the page URL. No credentials are written to messages or logs.
 *
 * @module     tool_flexaccess/nvda/support
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const path = require('path');
const { execFileSync } = require('child_process');
const { expect } = require('@playwright/test');
const { WindowsKeyCodes, WindowsModifiers } = require('@guidepup/guidepup');

/** What NVDA says for a role (English speech, as on the CI runner). */
const ROLE_SPEECH = {
  button: /\bbutton\b/,
  link: /\blink\b/,
  textbox: /\bedit\b/,
  combobox: /\bcombo ?box\b/,
  checkbox: /\bcheck ?box\b/,
  radio: /\bradio button\b/,
  menuitem: /\bmenu ?item\b|\blink\b|\bsubmenu\b/,
  tab: /\btab\b/,
};

/**
 * Lower case, letters and digits only, single spaces.
 *
 * @param {string} text Text.
 * @returns {string}
 */
function norm(text) {
  return String(text || '').toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
}

/**
 * Whether the speech contains the first words of a text (whole words, any punctuation in between).
 *
 * @param {string} said Speech.
 * @param {string} text Expected text.
 * @param {number} count Number of leading words that must be present.
 * @returns {boolean}
 */
function says(said, text, count = 4) {
  const haystack = ` ${norm(said)} `;
  const words = norm(text).split(' ').filter(Boolean).slice(0, count);
  return words.length > 0 && words.every((word) => haystack.includes(` ${word} `));
}

/**
 * Name, role and states of the focused element as the browser exposes them to assistive technology.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @returns {Promise<object>}
 */
async function focused(page) {
  const cdp = await page.context().newCDPSession(page);
  try {
    const { result } = await cdp.send('Runtime.evaluate', { expression: 'document.activeElement' });
    const { nodes } = await cdp.send('Accessibility.getPartialAXTree', { objectId: result.objectId, fetchRelatives: false });
    const node = nodes.find((n) => !n.ignored) || nodes[0] || {};
    const props = Object.fromEntries((node.properties || []).map((p) => [p.name, p.value && p.value.value]));
    const current = await page.evaluate(() => document.activeElement.getAttribute('aria-current'));
    return {
      role: node.role && node.role.value,
      name: (node.name && node.name.value) || '',
      description: (node.description && node.description.value) || '',
      current,
      ...props,
    };
  } finally {
    await cdp.detach();
  }
}

/** Everything NVDA said in the current test, step by step (attached to the report as evidence). */
const transcript = [];

/**
 * The transcript of the current test as text, and a fresh start for the next test.
 *
 * @returns {string}
 */
function takeTranscript() {
  const text = transcript.join('\n');
  transcript.length = 0;
  return text;
}

/**
 * The speech of the last NVDA command(s), joined.
 *
 * @param {object} nvda Guidepup NVDA instance.
 * @returns {Promise<string>}
 */
async function speech(nvda) {
  return (await nvda.spokenPhraseLog()).filter(Boolean).join(' | ');
}

/**
 * Press a key through NVDA and return what NVDA said in response (and nothing older).
 *
 * @param {object} nvda Guidepup NVDA instance.
 * @param {string} key Key, e.g. "Tab", "Shift+Tab", "Enter", "Space", "ArrowDown".
 * @returns {Promise<string>}
 */
async function press(nvda, key) {
  await nvda.clearSpokenPhraseLog();
  await nvda.press(key, { capture: true });
  const said = await speech(nvda);
  transcript.push(`[${key}] ${said}`);
  return said;
}

/**
 * Run an NVDA command and return what NVDA said in response.
 *
 * @param {object} nvda Guidepup NVDA instance.
 * @param {object} command Entry of nvda.keyboardCommands.
 * @returns {Promise<string>}
 */
async function perform(nvda, command) {
  await nvda.clearSpokenPhraseLog();
  await nvda.perform(command, { capture: true });
  const said = await speech(nvda);
  transcript.push(`[${command.description || command.representation || 'command'}] ${said}`);
  return said;
}

/**
 * Assert that NVDA announced the focused element: its accessible name, its role and the given states.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string} said What NVDA said after the focus moved there.
 * @param {object} [options] Options.
 * @param {RegExp[]} [options.states] Patterns the (normalised) speech must also match.
 * @param {number} [options.words] Leading words of the name that must be spoken.
 * @returns {Promise<object>} The focused element's facts.
 */
async function expectAnnounced(page, said, { states = [], words = 4 } = {}) {
  const node = await focused(page);
  const facts = `URL: ${page.url()}\nfocused element: ${JSON.stringify(node)}\nNVDA said: "${said}"`;
  expect(norm(node.name), `The focused element has no accessible name, NVDA cannot announce what it is.\n${facts}`)
    .not.toBe('');
  expect(says(said, node.name, words), `NVDA did not announce the name of the focused element.\n${facts}`).toBe(true);
  const role = ROLE_SPEECH[node.role];
  if (role) {
    expect(role.test(norm(said)), `NVDA did not announce the role "${node.role}".\n${facts}`).toBe(true);
  }
  for (const state of states) {
    expect(state.test(norm(said)), `NVDA did not announce ${state}.\n${facts}`).toBe(true);
  }
  return node;
}

/**
 * Put NVDA in browse mode at the top of the page's web content.
 *
 * Like Guidepup's navigateToWebContent(), but it never clicks into the page content: that helper clicks the
 * centre of <body>, which on a Moodle page can be a link or button and would navigate away. Here the browser
 * window is brought to the foreground (checked against the window title NVDA reports, switching applications
 * if needed), the page receives the OS focus through a temporary inert target, and NVDA moves to the top.
 *
 * @param {object} nvda Guidepup NVDA instance.
 * @param {import('@playwright/test').Page} page Page.
 * @returns {Promise<void>}
 */
async function toWebContent(nvda, page) {
  await nvda.perform(nvda.keyboardCommands.exitFocusMode, { capture: false });
  await page.bringToFront();
  const title = norm(await page.title());
  let foreground = false;
  for (let i = 0; i < 10 && !foreground; i++) {
    await nvda.clearSpokenPhraseLog();
    await nvda.perform(nvda.keyboardCommands.reportTitle, { capture: 'initial' });
    foreground = title !== '' && norm(await speech(nvda)).startsWith(title);
    if (!foreground) {
      await nvda.perform({ keyCode: [WindowsKeyCodes.Escape], modifiers: [WindowsModifiers.Alt] }, { capture: false });
      await page.waitForTimeout(500);
    }
  }
  if (!foreground) {
    throw new Error(`The browser window with "${title}" did not get the focus; NVDA reported "${await speech(nvda)}".`);
  }
  await page.evaluate(() => {
    const target = document.createElement('div');
    target.id = 'flexaccess-nvda-focus';
    target.setAttribute('aria-hidden', 'true');
    Object.assign(target.style, { position: 'fixed', left: '0', bottom: '0', width: '4px', height: '4px',
      zIndex: '2147483647' });
    document.body.appendChild(target);
  });
  await page.locator('#flexaccess-nvda-focus').click();
  await page.evaluate(() => document.getElementById('flexaccess-nvda-focus').remove());
  // Make sure NVDA is in browse mode (see Guidepup's helper for the reason of the double toggle).
  await nvda.perform(nvda.keyboardCommands.readNextFocusableItem, { capture: false });
  await nvda.perform(nvda.keyboardCommands.toggleBetweenBrowseAndFocusMode, { capture: false });
  await nvda.perform(nvda.keyboardCommands.toggleBetweenBrowseAndFocusMode, { capture: false });
  await nvda.perform(nvda.keyboardCommands.exitFocusMode, { capture: false });
  await nvda.press('Control+Home', { capture: false });
  await nvda.clearSpokenPhraseLog();
}

/**
 * Put the keyboard focus on the page's main region (start of a Tab sequence through the content).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @returns {Promise<void>}
 */
async function focusMain(page) {
  await page.evaluate(() => {
    const main = document.querySelector('#region-main') || document.querySelector('[role="main"], main');
    main.setAttribute('tabindex', '-1');
    main.focus();
  });
}

/**
 * Tab through the focusable elements of the main region with NVDA, asserting every announcement.
 *
 * @param {object} nvda Guidepup NVDA instance.
 * @param {import('@playwright/test').Page} page Page.
 * @param {number} max Upper bound of Tab presses.
 * @param {Function} [stop] (node, said) => true to stop after that element.
 * @returns {Promise<Array<{node: object, said: string}>>}
 */
async function tabThroughMain(nvda, page, max, stop = () => false) {
  await focusMain(page);
  const seen = [];
  for (let i = 0; i < max; i++) {
    const said = await press(nvda, 'Tab');
    const inside = await page.evaluate(() => !!document.activeElement.closest('#region-main, [role="main"], main'));
    if (!inside) {
      break;
    }
    const node = await expectAnnounced(page, said);
    seen.push({ node, said });
    if (stop(node, said)) {
      break;
    }
  }
  return seen;
}

/**
 * Move NVDA's browse-mode cursor item by item until all texts were spoken.
 *
 * @param {object} nvda Guidepup NVDA instance.
 * @param {string[]} texts Texts (leading words) that must be heard.
 * @param {number} max Upper bound of items.
 * @returns {Promise<string>} Everything heard.
 */
async function readUntil(nvda, texts, max = 80) {
  const heard = [];
  for (let i = 0; i < max; i++) {
    await nvda.clearSpokenPhraseLog();
    await nvda.next({ capture: true });
    heard.push(await speech(nvda));
    transcript.push(`[next item] ${heard[heard.length - 1]}`);
    if (texts.every((text) => says(heard.join(' '), text, 5))) {
      return heard.join(' | ');
    }
  }
  throw new Error(`NVDA did not read ${JSON.stringify(texts)} within ${max} items. Heard: "${heard.join(' | ')}"`);
}

/**
 * Move with NVDA's quick navigation to the main landmark (browse mode).
 *
 * @param {object} nvda Guidepup NVDA instance.
 * @returns {Promise<string>}
 */
async function toMain(nvda) {
  for (let i = 0; i < 8; i++) {
    const said = await perform(nvda, nvda.keyboardCommands.moveToNextLandmark);
    if (/\bmain\b/.test(norm(said))) {
      return said;
    }
  }
  throw new Error('NVDA did not reach the main landmark.');
}

/**
 * Sign in through Moodle's login form (setup step, not part of an NVDA assertion).
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string} username User name.
 * @param {string} password Password.
 * @returns {Promise<void>}
 */
async function login(page, username, password) {
  // A second attempt only for a login form that was replaced by a late response (the login token then no longer
  // matches); a wrong password fails twice and stops the journey.
  for (let attempt = 0; attempt < 2; attempt++) {
    await page.goto('/login/index.php', { waitUntil: 'networkidle' });
    await page.locator('#username').fill(username);
    await page.locator('#password').fill(password);
    await page.locator('#loginbtn').click();
    await page.waitForLoadState('load');
    if (!/\/login\/index\.php/.test(page.url())) {
      return;
    }
  }
  throw new Error(`The login of the fixture account failed (still on ${page.url()}).`);
}

/**
 * Run the CLI helper of the browser suite on the site under test.
 *
 * @param {...(string|number)} args Command and arguments.
 * @returns {object}
 */
function cli(...args) {
  const file = path.join(__dirname, '..', 'merge_cli.php');
  return JSON.parse(execFileSync('php', [file, ...args.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
}

module.exports = {
  ROLE_SPEECH, norm, says, focused, speech, takeTranscript, press, perform, expectAnnounced, toWebContent, focusMain, tabThroughMain,
  readUntil, toMain, login, cli,
};
