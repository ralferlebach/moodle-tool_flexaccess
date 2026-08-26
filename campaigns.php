<?php
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
 * Administrative management of FlexAccess invitation campaigns.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_flexaccess\local\campaign;

require_login();
$context = context_system::instance();
require_capability('tool/flexaccess:managecampaigns', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/flexaccess/campaigns.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('campaigns', 'tool_flexaccess'));
$PAGE->set_heading(get_string('pluginname', 'tool_flexaccess'));

$returnurl = new moodle_url('/admin/tool/flexaccess/campaigns.php');

// State-changing actions must be POSTed: a GET can be triggered by a prefetch or a shared link.
$ispost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($action === 'delete' && $id > 0 && $ispost && confirm_sesskey()) {
    campaign::delete($id);
    redirect($returnurl, get_string('campaigndeleted', 'tool_flexaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'confirmdelete' && $id > 0) {
    $campaign = campaign::get($id);
    if (!$campaign) {
        redirect($returnurl);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('campaigndeleteconfirm', 'tool_flexaccess', format_string($campaign->name)),
        new single_button(new moodle_url($returnurl, ['action' => 'delete', 'id' => $id]), get_string('delete'), 'post'),
        $returnurl
    );
    echo $OUTPUT->footer();
    die;
}

// Rotating a campaign link is the only way to recover from a lost link: the token is stored hashed,
// so it cannot be read back. Rotation invalidates the previous link immediately.
if ($action === 'rotate' && $id > 0 && $ispost && confirm_sesskey()) {
    $token = campaign::rotate_token($id);
    if ($token === null) {
        redirect($returnurl);
    }
    $link = (new moodle_url('/admin/tool/flexaccess/campaign.php', ['token' => $token]))->out(false);
    redirect(
        new moodle_url($returnurl, ['shown' => $id]),
        get_string('campaignlinkonce', 'tool_flexaccess', $link),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

if ($action === 'confirmrotate' && $id > 0) {
    $campaign = campaign::get($id);
    if (!$campaign) {
        redirect($returnurl);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('campaignrotateconfirm', 'tool_flexaccess', format_string($campaign->name)),
        new single_button(new moodle_url($returnurl, ['action' => 'rotate', 'id' => $id]), get_string('continue'), 'post'),
        $returnurl
    );
    echo $OUTPUT->footer();
    die;
}

$editing = ($action === 'edit' || $action === 'new');
$form = new \tool_flexaccess\form\campaign_form(
    new moodle_url($returnurl, ['action' => $action, 'id' => $id])
);

if ($action === 'edit' && $id > 0 && ($campaign = campaign::get($id))) {
    $form->set_data([
        'id' => $campaign->id,
        'name' => $campaign->name,
        'courseid' => $campaign->courseid,
        'enabled' => $campaign->enabled,
        'timeavailablefrom' => $campaign->timeavailablefrom,
        'timeavailableuntil' => $campaign->timeavailableuntil,
        'maxredemptions' => $campaign->maxredemptions,
        'gatemode' => $campaign->gatemode,
        'gatedomains' => $campaign->gatedomains,
    ]);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $values = [
        'name' => $data->name,
        'courseid' => $data->courseid,
        'enabled' => $data->enabled,
        'timeavailablefrom' => $data->timeavailablefrom,
        'timeavailableuntil' => $data->timeavailableuntil,
        'maxredemptions' => $data->maxredemptions,
        'gatemode' => $data->gatemode,
        'gatepassword' => $data->gatepassword ?? '',
        'gatedomains' => $data->gatedomains ?? '',
    ];
    if (!empty($data->id)) {
        campaign::update((int) $data->id, $values);
        redirect($returnurl, get_string('campaignsaved', 'tool_flexaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    // The plaintext link exists only here: it is stored hashed and can never be displayed again.
    $created = campaign::create($values);
    $link = (new moodle_url('/admin/tool/flexaccess/campaign.php', ['token' => $created['token']]))->out(false);
    redirect(
        $returnurl,
        get_string('campaignlinkonce', 'tool_flexaccess', $link),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('campaigns', 'tool_flexaccess'));

if ($editing) {
    $form->display();
    echo $OUTPUT->footer();
    die;
}

echo html_writer::tag('p', get_string('campaigns_intro', 'tool_flexaccess'));
echo $OUTPUT->single_button(new moodle_url($returnurl, ['action' => 'new']), get_string('campaignnew', 'tool_flexaccess'), 'get');

$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;
$totalcampaigns = campaign::count_all();
$campaigns = campaign::all($page * $perpage, $perpage);
if ($campaigns) {
    // Look up display names only for the courses shown on this page.
    $courseids = array_values(array_unique(array_map(static fn($c) => (int) $c->courseid, $campaigns)));
    $courses = $courseids
        ? $DB->get_records_list('course', 'id', $courseids, '', 'id, fullname')
        : [];
    $table = new html_table();
    $table->head = [
        get_string('campaignname', 'tool_flexaccess'),
        get_string('campaigncourse', 'tool_flexaccess'),
        get_string('campaignstatus', 'tool_flexaccess'),
        get_string('campaignredemptions', 'tool_flexaccess'),
        get_string('campaignlink', 'tool_flexaccess'),
        '',
    ];
    foreach ($campaigns as $campaign) {
        $status = campaign::is_redeemable($campaign) ? get_string('campaignopen', 'tool_flexaccess')
            : get_string('campaignclosed', 'tool_flexaccess');
        $count = (int) $campaign->redemptioncount
            . ((int) $campaign->maxredemptions > 0 ? ' / ' . (int) $campaign->maxredemptions : '');
        $editurl = new moodle_url($returnurl, ['action' => 'edit', 'id' => $campaign->id]);
        $deleteurl = new moodle_url($returnurl, ['action' => 'confirmdelete', 'id' => $campaign->id]);
        $rotateurl = new moodle_url($returnurl, ['action' => 'confirmrotate', 'id' => $campaign->id]);
        $actions = html_writer::link($editurl, get_string('edit'))
            . ' · '
            . html_writer::link($rotateurl, get_string('campaignrotate', 'tool_flexaccess'))
            . ' · '
            . html_writer::link($deleteurl, get_string('delete'));
        $table->data[] = [
            format_string($campaign->name),
            isset($courses[$campaign->courseid])
                ? format_string($courses[$campaign->courseid]->fullname)
                : ('#' . $campaign->courseid),
            $status,
            $count,
            get_string('campaignlinkhidden', 'tool_flexaccess'),
            $actions,
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($totalcampaigns, $page, $perpage, $returnurl);
} else {
    echo html_writer::tag('p', get_string('campaigns_none', 'tool_flexaccess'));
}

echo $OUTPUT->footer();
