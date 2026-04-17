<?php return array (
  'audit' => 
  array (
    'page_title' => 'Support Debug Audit Log',
    'column_time' => 'Time',
    'column_user' => 'User',
    'column_ip' => 'IP',
    'column_type' => 'Type',
    'column_command' => 'Command',
    'column_exit' => 'Exit',
    'column_duration' => 'Duration',
    'column_bytes' => 'Output bytes',
    'empty' => 'No audit log entries yet.',
  ),
  'endpoints' => 
  array (
    'heading' => 'Endpoints',
    'whoami' => 'Whoami (verify token + discover capabilities)',
    'bundle' => 'Bundle (full diagnostic snapshot)',
    'exec' => 'Execute (shell / tinker / artisan)',
    'logs' => 'Audit logs',
  ),
  'examples' => 
  array (
    'heading' => 'Usage examples',
    'curl_whoami' => 'Verify token',
    'curl_bundle' => 'Full diagnostic snapshot',
    'curl_exec_shell' => 'Run a shell command',
    'curl_exec_tinker' => 'Run tinker (PHP eval)',
  ),
  'flash' => 
  array (
    'toggle_yes' => 'Support debug API enabled.',
    'toggle_no' => 'Support debug API disabled.',
  ),
  'logs_heading' => 'Recent audit log',
  'page_subtitle' => 'Remote diagnostic endpoints for support engineering',
  'page_title' => 'Support Debug API',
  'toggle' => 
  array (
    'label' => 'Support debug API enabled',
    'help' => 'Allows admin api_token holders to call /api/v1/support/* — bundle (full diagnostic snapshot), exec (shell / tinker / artisan), and logs (audit log).',
    'enabled_note' => 'Endpoints are currently reachable. Disable when no support session is active.',
    'disabled_note' => 'Endpoints return 503. Enable while support engineer is diagnosing.',
    'button_enable' => 'Enable',
    'button_disable' => 'Disable',
  ),
  'token' => 
  array (
    'heading' => 'Admin API token',
    'help' => 'Share this token with the support engineer — it authenticates against /api/v1/support/*. Regenerate via Settings → Profile if exposed.',
    'reveal' => 'Reveal',
    'hide' => 'Hide',
    'copy' => 'Copy',
    'copied' => 'Copied!',
  ),
  'view_all_logs' => 'View all audit logs',
) ?>
