moodle-tool_flexaccess
======================

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-tool_flexaccess/actions/workflows/moodle-plugin-ci-main.yml/badge.svg?branch=main)](https://github.com/ralferlebach/moodle-tool_flexaccess/actions?query=workflow%3A%22Moodle+Plugin+CI+Main%22+branch%3Amain)

FlexAccess administration is the operator's view of the FlexAccess plugin set: account overview, mail queue, policies, invitations, campaigns and printable anonymous access lists.

FlexAccess is not a single plugin but a set of four that work as one system. They are released
together, carry the same version number and declare each other as dependencies, so they can only be
installed and updated as a set.

* **auth_flexaccess** provides the identity layer: it creates the temporary accounts, converts them into permanent ones, issues one-time login links and runs the central, rate-limited mail queue that all four plugins send through.
* **enrol_flexaccess** decides who may enter a course and how: it owns the access policy across site, category and course, enforces capacity, access windows, access keys and role or cohort restrictions.
* **mod_flexaccess** is the in-course entry point for keeping an account: it lets a temporary visitor convert their own account into a permanent one at the point in the course the teacher chooses.
* **tool_flexaccess** is the operator's view: account overview, mail queue, site and category policies, invitations, campaigns and printable anonymous access lists.

This README documents **tool_flexaccess** - the fourth bullet point above. The other three plugins are
documented in their own repositories.

Because the responsibilities are split this way, no rule exists twice: access is decided in one
place, identity is handled in another, and every mail leaves through one queue. That is also why a
partial installation does not work - a missing sibling means a missing part of the mechanism.


Requirements
------------

This plugin requires Moodle 4.5+

It also requires the other FlexAccess plugins. All four are released together and must be installed
in the same version (currently 1.0.0-RC1 / 2026082700):

* **auth_flexaccess (FlexAccess authentication)** - required dependency, declared in version.php\
  https://github.com/ralferlebach/moodle-auth_flexaccess
* **enrol_flexaccess (FlexAccess enrolment)** - required dependency, declared in version.php\
  https://github.com/ralferlebach/moodle-enrol_flexaccess
* **mod_flexaccess (FlexAccess activity)** - part of the same set; install it as well to use the complete feature range\
  https://github.com/ralferlebach/moodle-mod_flexaccess


Motivation for this plugin
--------------------------

The other three plugins decide what happens automatically. Someone still has to see what is going on, hand out access in a controlled way and clean up afterwards.

This tool provides that: who holds a temporary account and for how long, which mails are waiting or stuck, which invitations were accepted, and printable login cards for a room full of people who should get access without registering at all.


Installation
------------

Install the plugin like any other plugin to folder
/admin/tool/flexaccess

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, it is ready to use.

To use the plugin, please visit:
Site administration -> Users -> Accounts -> FlexAccess administration

There, you find:

* **Dashboard and account list** - all FlexAccess accounts with their state, searchable, with conversion to permanent accounts.
* **Mail queue** - what is queued, sent or failed, with a manual run.
* **Policies** - the site and category level of the FlexAccess access policy.
* **Invitations** - person-bound, single-use invitations including reminders and revocation.
* **Campaigns** - shareable links granting access to a course, with an optional redemption limit.
* **Anonymous access lists** - batches of generated accounts for a course, downloadable as one package containing a spreadsheet, a printable list and login cards.

If you want to learn more about using admin tool plugins in Moodle, please see https://docs.moodle.org/en/Admin_tools.


Capabilities
------------

This plugin also introduces these additional capabilities:

* **tool/flexaccess:viewdashboard** - View the FlexAccess dashboard. By default, this is assigned to managers.
* **tool/flexaccess:viewaccounts** - View the FlexAccess account list. By default, this is assigned to managers.
* **tool/flexaccess:convertaccounts** - Convert FlexAccess accounts into permanent accounts. By default, this is assigned to managers.
* **tool/flexaccess:managemailqueue** - View and run the FlexAccess mail queue. By default, this is assigned to managers.
* **tool/flexaccess:viewpolicies** - View the site and category access policies. By default, this is assigned to managers.
* **tool/flexaccess:managepolicies** - Change the site and category access policies. By default, this is assigned to managers.
* **tool/flexaccess:managecampaigns** - Create and revoke campaign links. By default, this is assigned to managers.
* **tool/flexaccess:manageinvitations** - Create, resend and revoke invitations. By default, this is assigned to managers.
* **tool/flexaccess:managebatches** - Provision anonymous access lists site-wide. By default, this is assigned to managers.
* **tool/flexaccess:managecoursebatches** - Manage anonymous access lists in a course. By default, this is assigned to managers.
* **tool/flexaccess:viewcoursebatches** - View a course's anonymous access lists. By default, this is assigned to managers and editing teachers.
* **tool/flexaccess:createcoursebatches** - Create anonymous access lists in a course. By default, this is assigned to managers.
* **tool/flexaccess:issuebatchcredentials** - Issue the credentials of an access list. This rotates passwords and is therefore a separate right. By default, this is assigned to managers.
* **tool/flexaccess:convertbatchaccounts** - Convert the accounts of an access list into permanent accounts. By default, this is assigned to managers.
* **tool/flexaccess:requestbatches** - Request an anonymous access list for a course. By default, this is assigned to editing teachers.


Scheduled Tasks
---------------

This plugin also introduces these additional scheduled tasks:

* **\tool_flexaccess\task\send_invitation_reminders** - Queues reminders for invitations that have not been accepted.\ By default, the task is enabled and runs daily.
* **\tool_flexaccess\task\provision_batch** and **\tool_flexaccess\task\convert_batch** - Ad-hoc tasks that create or convert large access lists in the background. They are queued on demand and have no schedule.


How this plugin works / Pitfalls
--------------------------------

An anonymous access list creates a set of generated accounts for one course. Small lists are provisioned immediately, larger ones in the background with a visible progress state. Each account is written only after it exists and is enrolled, so an interrupted run leaves a smaller but consistent list that a retry completes.

Passwords are never stored in clear. Issuing the credentials therefore rotates every password and produces one package - spreadsheet, printable list and login cards - from a single set. Because a second issue would invalidate the copies already handed out, the download is offered once; only a site administrator can issue again, and is warned about the consequence.

The login cards are laid out so the sheet can be cut: the gap between two cards is twice the page margin, so halving the sheet and halving again leaves every card with the same border. A free text supplied when the list is requested is printed on each card.

**Pitfall:** invitations and campaign links are bearer secrets. Only their hash is stored, so a lost link cannot be recovered - it can only be replaced, which invalidates the old one.


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is not published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/ralferlebach/moodle-tool_flexaccess


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/ralferlebach/moodle-tool_flexaccess/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


Feature proposals
-----------------

Due to limited resources, the functionality of this plugin is primarily implemented for our own local needs and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/ralferlebach/moodle-tool_flexaccess/issues

Please create pull requests on Github:
https://github.com/ralferlebach/moodle-tool_flexaccess/pulls

We are always interested to read about your feature proposals or even get a pull request from you, but please accept that we can handle your issues only as feature _proposals_ and not as feature _requests_.


Moodle release support
----------------------

Due to limited resources, this plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

This plugin is designed to be compatible with all currently supported versions of Moodle, leveraging its latest APIs. However, if you are using a legacy version of Moodle, we kindly advise against installing or using this plugin. Instead, we strongly recommend updating your Moodle instance to a supported version to ensure security and compliance with current technological standards. Thank you for your understanding.


Translating this plugin
-----------------------

This Moodle plugin is provided with English and German language packs only. Translations into other languages must be managed through AMOS (https://lang.moodle.org), where they will become part of Moodle's official language pack.

As the plugin creator, we continue to maintain the German translation. For all other languages, we kindly ask you to contribute your translations directly in AMOS. These contributions will be reviewed by Moodle's official language pack maintainers before being included in the official repository.

Thank you for supporting the global Moodle community!


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

The plugin is maintained by\
Ralf Erlebach

Copyright
---------

The copyright of this plugin is held by\
Ralf Erlebach

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
