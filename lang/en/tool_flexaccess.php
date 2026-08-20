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
 * Language strings.
 *
 * @package    tool_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accountactions'] = 'Actions';
$string['accountconvert'] = 'Convert to authenticated';
$string['accountconverted'] = 'The account was converted to an authenticated user.';
$string['accountemail'] = 'E-mail';
$string['accountname'] = 'Name';
$string['accountnotconverted'] = 'The account could not be converted (already authenticated or missing).';
$string['accounts'] = 'Accounts';
$string['accountsearch'] = 'Search name, email or reference number';
$string['accountsnone'] = 'No FlexAccess accounts match the current filter.';
$string['accountstate'] = 'State';
$string['accountstate_active'] = 'Active';
$string['accountstate_ephemeral'] = 'Ephemeral';
$string['accountstate_expired'] = 'Expired';
$string['accountstate_provisional'] = 'Provisional';
$string['accountstate_suspended'] = 'Suspended';
$string['accounttype'] = 'Type';
$string['accounttype_authenticated'] = 'Authenticated';
$string['accounttype_temporary'] = 'Temporary';
$string['accounttypeauthenticated'] = 'Authenticated users';
$string['accounttypetemporary'] = 'Temporary users';
$string['authunavailable'] = 'The auth_flexaccess plugin is not installed, so account data is unavailable.';
$string['campaignbadgate'] = 'This invitation is restricted. Please check the access password or use an eligible email address.';
$string['campaignbadwindow'] = 'The end date must be after the start date.';
$string['campaignclosed'] = 'Closed';
$string['campaigncopylink'] = 'Open link';
$string['campaigncourse'] = 'Target course';
$string['campaigndeleted'] = 'Campaign deleted.';
$string['campaignenabled'] = 'Enabled';
$string['campaignfrom'] = 'Available from';
$string['campaigngate'] = 'Access gate';
$string['campaigngatedomains'] = 'Allowed email domains';
$string['campaigngatepassword'] = 'Shared password';
$string['campaignintro'] = 'You have been invited to join {$a}. Create an account below to get started.';
$string['campaigninvitation'] = 'Course invitation';
$string['campaignlink'] = 'Invitation link';
$string['campaignmax'] = 'Maximum redemptions';
$string['campaignmax_help'] = 'The number of successful sign-ups this campaign allows. Set to 0 for no limit.';
$string['campaignname'] = 'Campaign name';
$string['campaignnew'] = 'New campaign';
$string['campaignopen'] = 'Open';
$string['campaignredemptions'] = 'Redemptions';
$string['campaigns'] = 'Invitation campaigns';
$string['campaigns_intro'] = 'Create shareable invitation links that let people self-register into a course through FlexAccess quick registration. Each link can be time-boxed, capped, and gated by a shared password or an allowed email domain.';
$string['campaigns_none'] = 'No campaigns have been created yet.';
$string['campaignsaved'] = 'Campaign saved.';
$string['campaignstatus'] = 'Status';
$string['campaignunavailable'] = 'This invitation link is not valid or is no longer available.';
$string['campaignuntil'] = 'Available until';
$string['convertemail'] = 'Email address';
$string['convertemail_help'] = 'The real email address of the person behind this temporary account. A set-password link is sent here.';
$string['convertfirstname'] = 'First name';
$string['convertheading'] = 'Convert temporary account';
$string['convertintro'] = 'Enter the person\'s real email address. Their identity is updated and they are emailed a link to set a password, so they can log in again.';
$string['convertinvalidemail'] = 'Enter a valid email address.';
$string['convertlastname'] = 'Last name';
$string['convertstatus:emailtaken'] = 'That email address is already in use by another account.';
$string['convertstatus:invalidemail'] = 'The email address is not valid.';
$string['convertstatus:notapplicable'] = 'This account is no longer a temporary account.';
$string['dashaccounts'] = 'Accounts';
$string['dashboard'] = 'Dashboard';
$string['dashexpired'] = 'Expired';
$string['dashmail'] = 'Mail queue';
$string['dashnextdue'] = 'Next mail due';
$string['dashnone'] = 'None scheduled';
$string['dashprovisional'] = 'Provisional';
$string['dashtotal'] = 'Total accounts';
$string['flexaccess:convertaccounts'] = 'Convert FlexAccess accounts to authenticated users';
$string['flexaccess:managecampaigns'] = 'Manage FlexAccess invitation campaigns';
$string['flexaccess:manageinvitations'] = 'Manage FlexAccess invitations';
$string['flexaccess:managemailqueue'] = 'Manage the FlexAccess mail queue';
$string['flexaccess:managepolicies'] = 'Manage FlexAccess category policies';
$string['flexaccess:viewaccounts'] = 'View FlexAccess accounts';
$string['flexaccess:viewdashboard'] = 'View the FlexAccess dashboard';
$string['flexaccess:viewpolicies'] = 'View FlexAccess policy diagnostics';
$string['gatedomain'] = 'Allowed email domains';
$string['gatenone'] = 'No additional gate';
$string['gatepassword'] = 'Shared password';
$string['invitations'] = 'Invitations';
$string['invitations_intro'] = 'Send person-bound, single-use invitations to specific email addresses. Each invitation can be sent, resent, reminded and revoked, and is consumed the moment the recipient registers.';
$string['invitations_none'] = 'No invitations have been created yet.';
$string['invite:actions'] = 'Actions';
$string['invite:course'] = 'Target course';
$string['invite:create'] = 'Create invitations';
$string['invite:created'] = '{$a} invitation(s) created.';
$string['invite:emailbody'] = 'You have been invited to join a course. To accept, create your account using this single-use link (it may expire): {$a}';
$string['invite:emails'] = 'Recipient email addresses';
$string['invite:emails_help'] = 'One address per line (or separated by commas/semicolons). Each valid address becomes a separate single-use invitation.';
$string['invite:emailsubject'] = 'You are invited to join a course';
$string['invite:expiry'] = 'Expires after';
$string['invite:expiry_help'] = 'How long each invitation stays valid after it is created. Leave unset for no expiry.';
$string['invite:intro'] = 'You have been invited to join this course as {$a}. Create your account below to accept.';
$string['invite:noemails'] = 'Please enter at least one valid email address.';
$string['invite:recipient'] = 'Recipient';
$string['invite:remind'] = 'Remind';
$string['invite:reminded'] = 'Reminder queued.';
$string['invite:reminderbody'] = 'This is a reminder that your course invitation is still waiting. To accept, create your account using this link: {$a}';
$string['invite:remindersubject'] = 'Reminder: your course invitation is waiting';
$string['invite:remindfailed'] = 'No reminder could be sent for that invitation.';
$string['invite:resend'] = 'Resend';
$string['invite:revoke'] = 'Revoke';
$string['invite:revoked'] = 'Invitation revoked.';
$string['invite:send'] = 'Send';
$string['invite:sendnow'] = 'Send immediately';
$string['invite:sent'] = 'Invitation queued for sending.';
$string['invite:sentcol'] = 'Sent';
$string['invite:status_accepted'] = 'Accepted';
$string['invite:status_pending'] = 'Pending';
$string['invite:status_revoked'] = 'Revoked';
$string['invite:statuscol'] = 'Status';
$string['invite:title'] = 'Course invitation';
$string['invite:unavailable'] = 'This invitation link is not valid, has already been used, or has expired.';
$string['mailqueue'] = 'Mail queue';
$string['managepolicies'] = 'Manage category policies';
$string['managepolicies_edit'] = 'Edit category override';
$string['managepolicies_intro'] = 'Set FlexAccess policy overrides per course category. Categories inherit the site defaults unless overridden here; a course may override its category. Leaving every field on "Inherit" removes the override.';
$string['managepolicies_none'] = 'No category overrides are defined yet.';
$string['mqattempts'] = 'Attempts';
$string['mqfailed'] = 'Failed';
$string['mqnextrun'] = 'Next run';
$string['mqnone'] = 'No mail-queue entries match the current filter.';
$string['mqqueued'] = 'Queued';
$string['mqrecipient'] = 'Recipient';
$string['mqsent'] = 'Sent';
$string['mqstatus'] = 'Status';
$string['mqsummary'] = 'Queued: {$a->queued}, sent: {$a->sent}, failed: {$a->failed}.';
$string['mqtype'] = 'Type';
$string['pluginname'] = 'FlexAccess administration';
$string['policies'] = 'Policy diagnostics';
$string['policiesintro'] = 'Planned diagnostics include the effective temporary-user access-key scope (none/system/course) without exposing secret values or hashes.';
$string['policiesnocourse'] = 'Append ?courseid=NNN to this page to inspect the effective FlexAccess policy for a course.';
$string['policyaccesskeyscope'] = 'Access-key scope';
$string['policyallow'] = 'Allow';
$string['policyallowguest'] = 'Guest access offered';
$string['policyallownormallogin'] = 'Normal login offered';
$string['policyallowquick'] = 'Quick registration allowed';
$string['policyallowtemporary'] = 'Temporary access allowed';
$string['policyavailablefrom'] = 'Available from';
$string['policyavailableuntil'] = 'Available until';
$string['policycategory'] = 'Course category';
$string['policydeleted'] = 'Category policy override removed.';
$string['policydeny'] = 'Deny';
$string['policyinherit'] = 'Inherit';
$string['policymaxparticipants'] = 'Maximum participants';
$string['policyproperty'] = 'Property';
$string['policyprovisionallifetime'] = 'Provisional account lifetime';
$string['policysaved'] = 'Category policy saved.';
$string['policytargetenabled'] = 'FlexAccess enrolment enabled';
$string['policytemporarylifetime'] = 'Temporary account lifetime';
$string['policyunbounded'] = 'No limit';
$string['policyunlimited'] = 'Unlimited';
$string['policyvalue'] = 'Value';
$string['policyvisibility'] = 'Participant-list visibility';
$string['policyvisibilityhide'] = 'Hide';
$string['policyvisibilityshow'] = 'Show';
$string['privacy:metadata'] = 'The administration tool stores no personal data of its own. Personal data remains with the owning FlexAccess plugins.';
$string['privacy:metadata:campaign'] = 'Information about FlexAccess invitation campaigns.';
$string['privacy:metadata:campaign:name'] = 'The campaign name.';
$string['privacy:metadata:campaign:timemodified'] = 'The time the campaign was last modified.';
$string['privacy:metadata:campaign:usermodified'] = 'The administrator who last modified the campaign.';
$string['privacy:metadata:invite'] = 'Information about person-bound FlexAccess invitations.';
$string['privacy:metadata:invite:email'] = 'The email address the invitation was sent to.';
$string['privacy:metadata:invite:timecreated'] = 'The time the invitation was created.';
$string['privacy:metadata:invite:usermodified'] = 'The administrator who created or last modified the invitation.';
$string['task:invitationreminders'] = 'Send FlexAccess invitation reminders';
