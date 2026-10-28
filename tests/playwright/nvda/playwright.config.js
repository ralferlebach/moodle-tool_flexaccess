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
 * Playwright configuration of the NVDA journeys (Windows, real NVDA through Guidepup; tool_flexaccess #11).
 *
 * Headed browser, one worker, serial (a screen reader is a singleton). One retry at most. No traces and no videos:
 * a trace would contain the downloaded credential package of N08. Screenshots only of failures.
 */

const { devices } = require('@playwright/test');
const { withGuidepup } = require('@guidepup/playwright');

module.exports = withGuidepup({
  testDir: '.',
  timeout: 5 * 60 * 1000,
  expect: { timeout: 20 * 1000 },
  forbidOnly: true,
  retries: 1,
  reportSlowTests: null,
  outputDir: 'results',
  reporter: [['list'], ['json', { outputFile: 'results/nvda-results.json' }], ['html', { open: 'never', outputFolder: 'report' }]],
  use: {
    baseURL: process.env.FLEXACCESS_BASE_URL || 'http://127.0.0.1:8000',
    screenshot: 'only-on-failure',
    trace: 'off',
    video: 'off',
    navigationTimeout: 90 * 1000,
    actionTimeout: 30 * 1000,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
