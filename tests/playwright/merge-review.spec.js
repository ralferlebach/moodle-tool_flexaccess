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
 * A merge that is only registered - not performed by tool_mergeusers - offers no way to move the LTI
 * bindings of a live account (auth_flexaccess #14).
 *
 * The seed registers a review pair of two live accounts with an LTI binding on the account to be removed.
 * The LTI identities page lists the pair, explains why moving is not possible and offers no form for it.
 *
 * Generic consolidation (auth_flexaccess #13): the preview shows origins and the login that is kept and
 * lost; the approval needs an ownership proof; after tool_mergeusers merged a permanent FlexAccess account
 * into a local account, the person signs in with the local password and finds course and grade there.
 *
 * @module     tool_flexaccess/merge-review.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const http = require('http');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const { loginAs, freshSession } = require('./helpers');

const MANAGER_USER = process.env.FLEXACCESS_MANAGER_USER;
const MANAGER_PASS = process.env.FLEXACCESS_MANAGER_PASS;
const SOURCE_ID = process.env.FLEXACCESS_MERGE_REVIEW_SOURCE_ID;

test.describe('LTI identities: unconfirmed merges', () => {
  test.skip(!MANAGER_USER || !SOURCE_ID, 'The merge review fixture was not seeded.');

  test('a registered but unmerged pair offers no executable repoint action', async ({ page }) => {
    await loginAs(page, MANAGER_USER, MANAGER_PASS);
    const response = await page.goto('/admin/tool/flexaccess/ltiidentities.php');
    expect(response.status()).toBe(200);
    const row = page.locator('#region-main tr', { hasText: 'Unmerged Source' }).first();
    await expect(row).toBeVisible();
    await expect(row).toContainText('No confirmed merge');
    await expect(row).toContainText('Moving is not possible');
    await expect(row).toContainText('tool_mergeusers has not confirmed');
    // Neither the button nor the ownership confirmation of the repoint form is there.
    await expect(row.getByRole('button', { name: /Move LTI bindings/ })).toHaveCount(0);
    await expect(row.locator('input[name="action"][value="repoint"]')).toHaveCount(0);
    await expect(row.getByRole('checkbox', { name: /belong to the same person/ })).toHaveCount(0);
  });
});

/**
 * Run the consolidation CLI helper on the Moodle site under test.
 *
 * @param {...(string|number)} args Command and arguments.
 * @returns {object} Parsed JSON output.
 */
function mergeCli(...args) {
  const cli = path.join(__dirname, 'merge_cli.php');
  return JSON.parse(execFileSync('php', [cli, ...args.map(String)], { encoding: 'utf8' }).trim().split('\n').pop());
}

test.describe('Account consolidation (auth_flexaccess #13)', () => {
  const E = process.env;
  test.skip(!MANAGER_USER || !E.FLEXACCESS_MERGE_FLEX_ID, 'The consolidation fixture was not seeded.');

  test('the preview names origins and the login that ends', async ({ page }) => {
    await loginAs(page, MANAGER_USER, MANAGER_PASS);
    await page.goto(`/admin/tool/flexaccess/ltiidentities.php?action=review&fromid=${E.FLEXACCESS_MERGE_FLEX_ID}`
      + `&toid=${E.FLEXACCESS_MERGE_SSO_ID}`);
    const main = page.locator('#region-main');
    await expect(main).toContainText('Origin: permanent FlexAccess account with password');
    await expect(main).toContainText('Origin: institutional login (single sign-on)');
    await expect(main).toContainText('Login that is kept: institutional login (single sign-on).');
    await expect(main).toContainText('Login of the removed account that ends with the merge: password login.');
    // The reverse direction would lose the institutional login: refused, no approval form.
    await page.goto(`/admin/tool/flexaccess/ltiidentities.php?action=review&fromid=${E.FLEXACCESS_MERGE_SSO_ID}`
      + `&toid=${E.FLEXACCESS_MERGE_FLEX_ID}`);
    await expect(main).toContainText('uses an institutional login, which would be lost');
    await expect(page.getByRole('button', { name: 'Approve merge' })).toHaveCount(0);
  });

  test('approve with ownership proof, merge, and sign in with the surviving login', async ({ page }) => {
    test.skip(E.FLEXACCESS_MERGEUSERS !== '1', 'tool_mergeusers is not installed on this site.');
    const flex = E.FLEXACCESS_MERGE_FLEX_ID;
    const keep = E.FLEXACCESS_MERGE_KEEP_ID;
    // Without an approval FlexAccess refuses inside the merge; tool_mergeusers rolls back and the refusal is recorded.
    expect(mergeCli('merge', keep, flex).success).toBe(false);
    expect(mergeCli('registry', flex, keep).status).toBe('refused');

    await loginAs(page, MANAGER_USER, MANAGER_PASS);
    await page.goto(`/admin/tool/flexaccess/ltiidentities.php?action=review&fromid=${flex}&toid=${keep}`);
    const main = page.locator('#region-main');
    await expect(main).toContainText('Origin: local account with password');
    await expect(main).toContainText('Login that is kept: password login.');
    await page.getByLabel('Ownership proof').selectOption('documents');
    await page.getByRole('checkbox', { name: /belong to the same person/ }).check();
    // An e-mail address is no evidence: the browser lets it through (long enough), the server refuses it.
    await page.getByLabel('Evidence').fill('mona.keeper@example.invalid mona.flexible@example.invalid');
    await page.getByRole('button', { name: 'Approve merge' }).click();
    await expect(page.locator('.alert-danger, .alert-error').first()).toContainText('was not approved');

    await page.goto(`/admin/tool/flexaccess/ltiidentities.php?action=review&fromid=${flex}&toid=${keep}`);
    await page.getByLabel('Ownership proof').selectOption('reauthentication');
    await page.getByLabel('Evidence').fill('Mona logged in with both accounts at the help desk (ticket 4711).');
    await page.getByRole('checkbox', { name: /belong to the same person/ }).check();
    await page.getByRole('button', { name: 'Approve merge' }).click();
    await expect(page.locator('.alert-success').first()).toContainText('Merge approved');

    const merged = mergeCli('merge', keep, flex);
    expect(merged.success).toBe(true);
    expect(merged.status).toBe('reconciled');
    expect(Number(mergeCli('grade', E.FLEXACCESS_MERGE_GRADEITEM_ID, keep).finalgrade)).toBe(66);

    // The person signs in with the surviving local password and reaches the course of the removed account.
    await freshSession(page);
    await loginAs(page, 'mergekeep', E.FLEXACCESS_MERGE_KEEP_PASS);
    const course = await page.goto(`/course/view.php?id=${E.FLEXACCESS_MERGE_COURSE_ID}`);
    expect(course.status()).toBe(200);
    await expect(page.locator('h1, .page-header-headings').first()).toContainText('Consolidation course');
    await expect(page).not.toHaveURL(/enrol\/index\.php/);
  });
});

/**
 * A minimal OAuth 2 / OpenID identity provider for one person (test sites only).
 *
 * @param {number} port Port to listen on.
 * @returns {Promise<import('http').Server>}
 */
function startIdentityProvider(port) {
  const server = http.createServer((req, res) => {
    const url = new URL(req.url, `http://127.0.0.1:${port}`);
    if (url.pathname === '/authorize') {
      // The person is "logged in" at the provider: send the browser straight back with a code.
      const back = new URL(url.searchParams.get('redirect_uri'));
      back.searchParams.set('code', 'code-mona');
      back.searchParams.set('state', url.searchParams.get('state') || '');
      res.writeHead(302, { Location: back.toString() });
      res.end();
    } else if (url.pathname === '/token') {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ access_token: 'token-mona', token_type: 'Bearer', expires_in: 3600 }));
    } else if (url.pathname === '/userinfo') {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({
        sub: 'mona-sso-1',
        preferred_username: 'mona.sso',
        email: 'mona.sso@example.invalid',
        given_name: 'Mona',
        family_name: 'Institution',
      }));
    } else {
      res.writeHead(404);
      res.end();
    }
  });
  return new Promise((resolve) => server.listen(port, '127.0.0.1', () => resolve(server)));
}

/**
 * Sign in through the test identity provider from the login page.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @returns {Promise<void>}
 */
async function loginWithProvider(page) {
  await page.goto('/login/index.php');
  await page.getByRole('link', { name: process.env.FLEXACCESS_OAUTH_ISSUER }).first().click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page).not.toHaveURL(/\/login\/index\.php/);
}

for (const kind of ['flex', 'manual']) {
  test.describe(`Two permanent accounts (${kind} into FlexAccess, auth_flexaccess #13)`, () => {
    const E = process.env;
    test.skip(!MANAGER_USER || E.FLEXACCESS_MERGEUSERS !== '1', 'Fixture or tool_mergeusers missing.');

    test(`${kind} into permanent FlexAccess: the kept password reaches both courses, both grades stay`, async ({ page }) => {
      const pair = mergeCli('durablepair', kind);
      // No merge without an ownership proof, then the approval and the real merge through tool_mergeusers.
      expect(mergeCli('merge', pair.to, pair.from).success).toBe(false);
      expect(mergeCli('approve', pair.from, pair.to).approved).toBe(true);
      const merged = mergeCli('merge', pair.to, pair.from);
      expect(merged.success).toBe(true);
      expect(merged.status).toBe('reconciled');
      expect(Number(mergeCli('grade', pair.toitem, pair.to).finalgrade)).toBe(70);
      expect(Number(mergeCli('grade', pair.fromitem, pair.to).finalgrade)).toBe(55);

      await freshSession(page);
      await loginAs(page, pair.tousername, pair.password);
      for (const course of [pair.tocourse, pair.fromcourse]) {
        const response = await page.goto(`/course/view.php?id=${course}`);
        expect(response.status()).toBe(200);
        await expect(page).not.toHaveURL(/enrol\/index\.php|login\/index\.php/);
      }
      // The removed account's own password no longer logs anybody in.
      await freshSession(page);
      await loginAs(page, pair.fromusername, 'Other-Pass!1');
      await expect(page).toHaveURL(/login\/index\.php/);
    });
  });
}

test.describe('Ownership proof and institutional login (auth_flexaccess #13)', () => {
  const E = process.env;
  test.skip(!E.FLEXACCESS_MERGE_VISITOR_ID || E.FLEXACCESS_MERGEUSERS !== '1', 'Fixture or tool_mergeusers missing.');

  test('a temporary visitor is never merged without an ownership proof', async ({ page }) => {
    const visitor = E.FLEXACCESS_MERGE_VISITOR_ID;
    const keep = E.FLEXACCESS_MERGE_KEEP_ID;
    const merged = mergeCli('merge', keep, visitor);
    expect(merged.success).toBe(false);
    const registry = mergeCli('registry', visitor, keep);
    expect(registry.status).toBe('refused');
    expect(registry.reasons).toContain('needsapproval');
    // The review page asks for the proof; there is no way around it for temporary accounts.
    await loginAs(page, MANAGER_USER, MANAGER_PASS);
    await page.goto(`/admin/tool/flexaccess/ltiidentities.php?action=review&fromid=${visitor}&toid=${keep}`);
    await expect(page.locator('#region-main')).toContainText('Origin: temporary FlexAccess access');
    await expect(page.getByLabel('Ownership proof')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Approve merge' })).toBeVisible();
  });

  test('after merging the FlexAccess account into the institutional one, the provider login reaches it', async ({ browser }) => {
    test.skip(!E.FLEXACCESS_OAUTH_PORT, 'No identity provider fixture.');
    const idp = await startIdentityProvider(Number(E.FLEXACCESS_OAUTH_PORT));
    try {
      // 1. First login through the provider creates the institutional account and its linked login.
      const first = await browser.newContext();
      await loginWithProvider(await first.newPage());
      await first.close();
      const sso = mergeCli('user', 'mona.sso');
      expect(sso.auth).toBe('oauth2');
      expect(mergeCli('linked', 'mona.sso').userid).toBe(sso.id);

      // 2. Approved with an ownership proof, the FlexAccess account is merged into the institutional one.
      const flex = E.FLEXACCESS_SSO_FLEX_ID;
      expect(mergeCli('merge', sso.id, flex).success).toBe(false);
      expect(mergeCli('approve', flex, sso.id).approved).toBe(true);
      const merged = mergeCli('merge', sso.id, flex);
      expect(merged.success).toBe(true);
      expect(merged.status).toBe('reconciled');

      // 3. The next login through the provider lands on the same, kept account - with the course of the
      //    removed FlexAccess account.
      const again = await browser.newContext();
      const page = await again.newPage();
      await loginWithProvider(page);
      expect(mergeCli('linked', 'mona.sso').userid).toBe(sso.id);
      expect(mergeCli('user', 'mona.sso').id).toBe(sso.id);
      const course = await page.goto(`/course/view.php?id=${E.FLEXACCESS_SSO_COURSE_ID}`);
      expect(course.status()).toBe(200);
      await expect(page.locator('h1, .page-header-headings').first()).toContainText('Institutional login course');
      await expect(page).not.toHaveURL(/enrol\/index\.php/);
      await again.close();
    } finally {
      await new Promise((resolve) => idp.close(resolve));
    }
  });
  test('conflicts and recorded enrolments are decided separately, and no credential is shown', async ({ page }) => {
    const pair = mergeCli('pendingpair', E.FLEXACCESS_MERGE_COURSE_ID);
    expect(pair.status).toBe('review');
    expect(pair.reasons).toEqual(expect.arrayContaining(['conflictsrecorded', 'enrolmentspending']));
    await loginAs(page, MANAGER_USER, MANAGER_PASS);
    const review = `/admin/tool/flexaccess/ltiidentities.php?action=review&fromid=${pair.from}&toid=${pair.to}`;
    await page.goto(review);
    const main = page.locator('#region-main');
    // The archived grade is shown; the credential an earlier version archived with it is not.
    await expect(main).toContainText('55.00000');
    await expect(main).not.toContainText('LEGACY-SECRET');
    await expect(main).toContainText('Recorded FlexAccess enrolments');
    await expect(main).toContainText('Could not be taken over.');

    // The general confirmation closes the grade conflict only.
    await page.goto('/admin/tool/flexaccess/ltiidentities.php');
    const row = page.locator('#region-main tr', { hasText: pair.name }).first();
    await expect(row.getByRole('link', { name: 'Decide on enrolments' })).toBeVisible();
    await row.getByRole('button', { name: 'Conflicts reviewed' }).click();
    await expect(page.locator('.alert').first()).toContainText('1 conflict record(s) marked as reviewed');
    let registry = mergeCli('registry', pair.from, pair.to);
    expect(registry.status).toBe('review');
    expect(registry.reasons).toContain('enrolmentspending');
    expect(registry.reasons).not.toContain('conflictsrecorded');

    // The enrolment needs its own decision with a reason.
    await page.goto(review);
    const form = page.locator(`form:has(input[name="action"][value="discardenrolment"])`);
    await expect(form).toHaveCount(1);
    await form.locator('textarea[name="decisionreason"]').fill('Course closed; the person confirmed in writing not to need it.');
    await form.getByRole('button', { name: 'Do not take over' }).click();
    await expect(page.locator('.alert').first()).toContainText('is not taken over');
    registry = mergeCli('registry', pair.from, pair.to);
    expect(registry.status).toBe('reconciled');
    await expect(page.locator('form:has(input[name="action"][value="discardenrolment"])')).toHaveCount(0);
  });
});
