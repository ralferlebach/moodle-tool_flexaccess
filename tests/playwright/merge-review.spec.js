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
 * @module     tool_flexaccess/merge-review.spec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const { test, expect } = require('@playwright/test');
const { loginAs } = require('./helpers');

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
