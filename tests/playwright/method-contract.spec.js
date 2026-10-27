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
 * Changing actions are POST only (tool_flexaccess #14): at the real entry points, a GET or HEAD with a valid
 * sesskey from an authorised administrator changes nothing - accounts, credentials, mail queue, merge register,
 * LTI bindings, enrolments - while the same request as POST performs exactly the intended change.
 *
 * @module     tool_flexaccess/method-contract.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

/**
 * Sign in as the manager and read the session key of the page.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @returns {Promise<string>} sesskey.
 */
async function sesskey(page) {
  await loginAs(page, E.FLEXACCESS_MANAGER_USER, E.FLEXACCESS_MANAGER_PASS);
  await page.goto('/admin/tool/flexaccess/ltiidentities.php');
  return page.evaluate(() => window.M.cfg.sesskey);
}

/**
 * Query string from parameters (arrays become repeated keys).
 *
 * @param {object} params Parameters.
 * @returns {string}
 */
function query(params) {
  const q = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    for (const v of [].concat(value)) {
      q.append(key, String(v));
    }
  }
  return q.toString();
}

test.describe('Changing actions are POST only (tool_flexaccess #14)', () => {
  test.skip(!E.FLEXACCESS_MANAGER_USER || E.FLEXACCESS_MERGEUSERS !== '1', 'The fixture was not seeded.');

  test('LTI identities: GET and HEAD with a valid sesskey change nothing; POST changes exactly one thing', async ({ page }) => {
    const key = await sesskey(page);
    const pair = cli('pendingpair', E.FLEXACCESS_MERGE_COURSE_ID);
    const temp = cli('tempbinding');
    const before = { pair: cli('state', pair.from, pair.to), temp: cli('state', temp.id, 0) };
    const base = '/admin/tool/flexaccess/ltiidentities.php';
    const actions = [
      { action: 'approve', fromid: pair.from, toid: pair.to, ownershipverified: 1, prooftype: 'documents',
        proofevidence: 'Checked the photo ID at the help desk, ticket 9.' },
      { action: 'acknowledge', fromid: pair.from, toid: pair.to },
      { action: 'discardenrolment', fromid: pair.from, toid: pair.to, evidenceid: 1,
        decisionreason: 'Not needed any more, confirmed by the person.' },
      { action: 'recheck', fromid: pair.from, toid: pair.to },
      { action: 'repoint', fromid: pair.from, toid: pair.to, ownershipverified: 1 },
      { action: 'removebinding', bindingid: temp.bindingid },
    ];
    for (const params of actions) {
      const url = `${base}?${query({ ...params, sesskey: key })}`;
      await page.request.get(url);
      await page.request.fetch(url, { method: 'HEAD' });
      expect(cli('state', pair.from, pair.to), `GET ${params.action}`).toEqual(before.pair);
      expect(cli('state', temp.id, 0), `GET ${params.action}`).toEqual(before.temp);
    }
    // The same request as POST: the binding is removed, nothing else of the pair changes.
    await page.request.post(base, { form: { action: 'removebinding', bindingid: temp.bindingid, sesskey: key } });
    expect(cli('state', temp.id, 0).bindings).toBe(0);
    // POST without a valid sesskey fails closed.
    await page.request.post(base, { form: { action: 'acknowledge', fromid: pair.from, toid: pair.to, sesskey: 'invalid' } });
    expect(cli('state', pair.from, pair.to)).toEqual(before.pair);
    // With it, exactly the review conflict is acknowledged.
    await page.request.post(base, { form: { action: 'acknowledge', fromid: pair.from, toid: pair.to, sesskey: key } });
    expect(cli('state', pair.from, pair.to).evidenceopen).toBe(before.pair.evidenceopen - 1);
  });

  test('LTI identities: without the right to change users, a valid POST changes nothing (auth #14)', async ({ page }) => {
    const pair = cli('pendingpair', E.FLEXACCESS_MERGE_COURSE_ID);
    const temp = cli('tempbinding');
    const before = { pair: cli('state', pair.from, pair.to), temp: cli('state', temp.id, 0) };
    const reviewer = cli('reviewonly');
    await loginAs(page, reviewer.username, reviewer.password);
    const base = '/admin/tool/flexaccess/ltiidentities.php';
    const response = await page.goto(base);
    expect(response.status()).toBe(200);
    const key = await page.evaluate(() => window.M.cfg.sesskey);
    for (const params of [
      { action: 'approve', fromid: pair.from, toid: pair.to, ownershipverified: 1, prooftype: 'documents',
        proofevidence: 'Checked the photo ID at the help desk, ticket 10.' },
      { action: 'repoint', fromid: pair.from, toid: pair.to, ownershipverified: 1 },
      { action: 'acknowledge', fromid: pair.from, toid: pair.to },
      { action: 'removebinding', bindingid: temp.bindingid },
    ]) {
      const result = await page.request.post(base, { form: { ...params, sesskey: key } });
      expect(await result.text(), params.action).toMatch(/permission|Berechtigung|nicht erlaubt|not allowed/i);
      expect(cli('state', pair.from, pair.to), params.action).toEqual(before.pair);
      expect(cli('state', temp.id, 0), params.action).toEqual(before.temp);
    }
  });

  test('user page: resending the set-password link only by POST', async ({ page }) => {
    const key = await sesskey(page);
    const account = cli('pendingcredential');
    expect(account.status).toBe('converted');
    const before = cli('state', account.id, 0);
    const url = `/admin/tool/flexaccess/user.php?${query({ userid: account.id, action: 'resendsetpassword', sesskey: key })}`;
    await page.request.get(url);
    await page.request.fetch(url, { method: 'HEAD' });
    expect(cli('state', account.id, 0)).toEqual(before);
    // The button on the page carries no sesskey in a shareable URL.
    await page.goto(`/admin/tool/flexaccess/user.php?userid=${account.id}`);
    const form = page.locator('form:has(input[name="action"][value="resendsetpassword"])');
    await expect(form).toHaveAttribute('method', /post/i);
    expect(await form.getAttribute('action')).not.toContain('sesskey');
    await form.getByRole('button').click();
    expect(cli('state', account.id, 0).mail).toBe(before.mail + 1);
  });

  test('recovery: a confirmed recovery runs only as POST', async ({ page }) => {
    const key = await sesskey(page);
    const user = cli('suspendedue', E.FLEXACCESS_MERGE_COURSE_ID);
    const before = cli('state', user.id, 0);
    expect(before.enrolments[0][0]).toBe(1);
    const params = { confirm: 1, sesskey: key, 'userids[]': user.id, 'reactivate[]': `${user.id}:${user.ueid}` };
    const url = `/admin/tool/flexaccess/recover.php?${query(params)}`;
    await page.request.get(url);
    await page.request.fetch(url, { method: 'HEAD' });
    expect(cli('state', user.id, 0)).toEqual(before);
    await page.request.post('/admin/tool/flexaccess/recover.php', { form: params });
    expect(cli('state', user.id, 0).enrolments[0][0]).toBe(0);
  });
});
