# AI Valve — Developer Hooks

AI Valve exposes three hooks that let you extend or react to its decisions without touching the plugin's source code.

---

## Filters

### `ai_valve_plugin_policy`

Dynamically override the allow/deny policy for any plugin.

Fires during the policy evaluation step, after the stored setting is read but before the decision is applied. Return `'allow'` or `'deny'`.

**Signature**

```php
apply_filters( 'ai_valve_plugin_policy', string $policy, string $plugin_slug, string $context )
```

| Parameter | Type | Description |
|---|---|---|
| `$policy` | `string` | Current policy: `'allow'` or `'deny'` (from settings). |
| `$plugin_slug` | `string` | Slug of the plugin making the AI request. |
| `$context` | `string` | Execution context: `admin`, `frontend`, `cron`, `rest`, `ajax`, or `cli`. |

**Examples**

Deny a specific plugin regardless of what the Settings UI says:

```php
add_filter( 'ai_valve_plugin_policy', function ( string $policy, string $slug ): string {
    if ( 'my-untrusted-plugin' === $slug ) {
        return 'deny';
    }
    return $policy;
}, 10, 2 );
```

Allow a plugin only when running in the admin context:

```php
add_filter( 'ai_valve_plugin_policy', function ( string $policy, string $slug, string $context ): string {
    if ( 'my-plugin' === $slug && 'admin' !== $context ) {
        return 'deny';
    }
    return $policy;
}, 10, 3 );
```

Allow all plugins unconditionally (effectively disable the deny list):

```php
add_filter( 'ai_valve_plugin_policy', fn() => 'allow' );
```

---

## Actions

### `ai_valve_request_denied`

Fires immediately after AI Valve blocks an AI request.

Use this to log denials to an external system, trigger a notification, or increment your own counters.

**Signature**

```php
do_action( 'ai_valve_request_denied', string $plugin_slug, string $context, string $reason )
```

| Parameter | Type | Description |
|---|---|---|
| `$plugin_slug` | `string` | The plugin that attempted the AI request. |
| `$context` | `string` | Execution context at the time of the request. |
| `$reason` | `string` | Denial reason code (see table below). |

**Reason codes**

| Code | Cause |
|---|---|
| `ai_valve_disabled` | The master switch is off. |
| `plugin_denied` | The plugin's policy is `deny` (including via the `ai_valve_plugin_policy` filter). |
| `context_denied` | The execution context is not allowed in Settings. |
| `plugin_daily_budget_exceeded` | Plugin hit its per-day token limit. |
| `plugin_monthly_budget_exceeded` | Plugin hit its per-month token limit. |
| `global_daily_budget_exceeded` | Site-wide daily token limit reached. |
| `global_monthly_budget_exceeded` | Site-wide monthly token limit reached. |

**Examples**

Send a Slack message when a plugin is blocked:

```php
add_action( 'ai_valve_request_denied', function ( string $slug, string $context, string $reason ): void {
    if ( str_starts_with( $reason, 'plugin_' ) ) {
        wp_remote_post( SLACK_WEBHOOK_URL, [
            'body' => wp_json_encode( [
                'text' => "AI Valve blocked *{$slug}* in context *{$context}* — reason: `{$reason}`",
            ] ),
        ] );
    }
}, 10, 3 );
```

Write denials to a custom log table:

```php
add_action( 'ai_valve_request_denied', function ( string $slug, string $context, string $reason ): void {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'my_ai_denials',
        [
            'plugin_slug' => $slug,
            'context'     => $context,
            'reason'      => $reason,
            'denied_at'   => current_time( 'mysql', true ),
        ],
        [ '%s', '%s', '%s', '%s' ]
    );
}, 10, 3 );
```

---

### `ai_valve_request_completed`

Fires after every successful AI request, once the token usage has been recorded.

Use this to push token data to analytics, enforce additional post-request logic, or sync usage to an external billing system.

**Signature**

```php
do_action(
    'ai_valve_request_completed',
    string $plugin_slug,
    string $provider_id,
    string $model_id,
    string $capability,
    int    $prompt_tokens,
    int    $completion_tokens,
    int    $total_tokens,
    int    $duration_ms
)
```

| Parameter | Type | Description |
|---|---|---|
| `$plugin_slug` | `string` | The plugin that made the AI request. |
| `$provider_id` | `string` | Provider identifier (e.g. `openai`, `azure-openai`). |
| `$model_id` | `string` | Model identifier (e.g. `gpt-4o`, `claude-3-5-sonnet`). |
| `$capability` | `string` | Capability used (e.g. `text-generation`). |
| `$prompt_tokens` | `int` | Tokens consumed by the input prompt. |
| `$completion_tokens` | `int` | Tokens consumed by the model's response. |
| `$total_tokens` | `int` | Sum of prompt and completion tokens. |
| `$duration_ms` | `int` | Wall-clock time for the request in milliseconds. |

**Examples**

Push token usage to a monitoring endpoint:

```php
add_action(
    'ai_valve_request_completed',
    function (
        string $slug,
        string $provider,
        string $model,
        string $capability,
        int $prompt,
        int $completion,
        int $total,
        int $ms
    ): void {
        wp_remote_post( 'https://metrics.example.com/ai-usage', [
            'body' => wp_json_encode( compact( 'slug', 'provider', 'model', 'total', 'ms' ) ),
        ] );
    },
    10,
    8
);
```

Emit a warning if a single request is unusually expensive:

```php
add_action(
    'ai_valve_request_completed',
    function ( string $slug, string $provider, string $model, string $capability, int $prompt, int $completion, int $total ): void {
        if ( $total > 10000 ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( "AI Valve: large request from {$slug} — {$total} tokens on {$provider}/{$model}" );
        }
    },
    10,
    7
);
```
