# How Blocking Works

When a WordPress plugin calls `wp_ai_client_prompt()`, AI Valve evaluates the request **before** it reaches the AI provider. If the request violates any policy, it is blocked immediately — no tokens are consumed.

## What happens when a request is blocked

1. AI Valve's `PolicyEngine::evaluate()` returns `false`.
2. `RequestInterceptor` returns a `WP_Error` with code `prompt_prevented` and a message containing the denial reason.
3. The calling plugin receives this `WP_Error` instead of an AI response.
4. The request is logged in the AI Valve log with a status of `denied:<reason>`.

The calling plugin is responsible for handling the `WP_Error` gracefully (e.g. showing a fallback message or silently skipping the AI feature).

## Denial reasons

Policy checks run in order. The **first** failing check blocks the request:

| # | Reason | Meaning |
|---|--------|---------|
| 1 | `ai_valve_disabled` | The master switch is off — all AI requests are blocked. |
| 2 | `plugin_denied` | The plugin's per-plugin policy is set to **Deny**. |
| 3 | `context_denied` | The current execution context (admin, frontend, cron, REST, AJAX, CLI) is not in the allowed list. |
| 4 | `plugin_daily_budget_exceeded` | The plugin has used all of its per-plugin daily token budget. |
| 5 | `plugin_monthly_budget_exceeded` | The plugin has used all of its per-plugin monthly token budget. |
| 6 | `global_daily_budget_exceeded` | The site-wide daily token budget has been reached. |
| 7 | `global_monthly_budget_exceeded` | The site-wide monthly token budget has been reached. |

## When do budgets reset?

- **Daily budgets** reset at midnight (server time) each day.
- **Monthly budgets** reset on the first day of each calendar month.

A limit of **0** means unlimited — no cap is enforced.

## Where to see blocked requests

Go to **Settings → AI Valve → Logs** and filter by **Status → Denied**. Each denied row shows the full denial reason, the calling plugin, and the execution context.

The **Dashboard** tab also shows denied requests in the context breakdown table.

## Tips for plugin developers

If your plugin calls `wp_ai_client_prompt()`, always check for `WP_Error`:

```php
$result = wp_ai_client_prompt( $prompt );

if ( is_wp_error( $result ) ) {
    // AI request was blocked or failed.
    // $result->get_error_code()    → 'prompt_prevented'
    // $result->get_error_message() → e.g. 'plugin_daily_budget_exceeded'
    return;
}

// Use $result normally.
```

This ensures your plugin degrades gracefully when AI Valve (or any other gating plugin) blocks the request.
