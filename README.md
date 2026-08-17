# tool_flexaccess

System-wide administration/operations scaffold for the Moodle FlexAccess ecosystem.

Responsibilities: account lookup/detail by reference number, administrative conversion/suspension/deletion orchestration, mail queue and rolling-hour throttle diagnostics/retry, and effective policy diagnostics. It **does not own or duplicate** account, queue, enrolment or policy data; mutations are delegated to public `auth_flexaccess` / `enrol_flexaccess` services.

Depends on `auth_flexaccess` and `enrol_flexaccess`. No runtime plugin depends on this admin tool.
