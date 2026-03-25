# How to Intercept WordPress 7 AI Connector Requests

A practical guide to hooking into the WP 7 AI client and correctly reading token usage from the SDK.

---

## The Three Hooks

WordPress 7 exposes three hooks for AI connector traffic:

| Hook | Type | Callback Signature | When It Fires |
|---|---|---|---|
| `wp_ai_client_prevent_prompt` | filter | `(bool $prevent, WP_AI_Client_Prompt_Builder $builder): bool` | Before the AI request is sent. Return `true` to block. |
| `wp_ai_client_before_generate_result` | action | `(BeforeGenerateResultEvent $event): void` | After gating, before the HTTP call to the provider. |
| `wp_ai_client_after_generate_result` | action | `(AfterGenerateResultEvent $event): void` | After a successful response, with token usage available. |

### Minimal Example

```php
add_filter( 'wp_ai_client_prevent_prompt', function ( bool $prevent, WP_AI_Client_Prompt_Builder $builder ): bool {
    // Block all AI calls from cron.
    if ( wp_doing_cron() ) {
        return true;
    }
    return $prevent;
}, 10, 2 );

add_action( 'wp_ai_client_after_generate_result', function ( AfterGenerateResultEvent $event ): void {
    $usage = $event->getResult()->getTokenUsage();
    error_log( sprintf( 'AI used %d tokens', $usage->getTotalTokens() ) );
}, 10, 1 );
```

---

## ~~WP 7.0 Core Bug: Event Dispatcher Not Injected~~ (Fixed in RC1)

> **Resolved.** WP 7.0 beta shipped without passing the event dispatcher to the SDK
> `PromptBuilder`. This caused `wp_ai_client_before_generate_result` and
> `wp_ai_client_after_generate_result` to never fire. AI Valve previously worked
> around the bug by injecting the dispatcher via reflection at priority 5 of the
> `wp_ai_client_prevent_prompt` filter.
>
> As of **WP 7 RC1**, the `WP_AI_Client_Prompt_Builder` constructor now passes
> `AiClient::getEventDispatcher()` to the SDK `PromptBuilder` directly. The
> reflection workaround has been removed from AI Valve.

---

## SDK API Reference (Correct Accessor Patterns)

The SDK uses **private properties with getter methods**. Direct property access (`$obj->property`) will fail silently or throw errors.

### TokenUsage

Returned by `$result->getTokenUsage()`. Always non-null.

| Method | Return |
|---|---|
| `getPromptTokens()` | `int` |
| `getCompletionTokens()` | `int` |
| `getTotalTokens()` | `int` |
| `getThoughtTokens()` | `?int` |

**Wrong:** `$usage->promptTokens`
**Right:** `$usage->getPromptTokens()`

### Model Metadata

Available on `BeforeGenerateResultEvent` and `AfterGenerateResultEvent` via `$event->getModel()`.

```php
$model = $event->getModel();

// Provider info (e.g. "azure_openai")
$provider_id = $model->providerMetadata()->getId();

// Model info (e.g. "gpt-4.1")
$model_id   = $model->metadata()->getId();
$model_name = $model->metadata()->getName();
```

### GenerativeAiResult

Available on `AfterGenerateResultEvent` via `$event->getResult()`.

```php
$result = $event->getResult();
$usage  = $result->getTokenUsage();       // TokenUsage (non-nullable)
```

### Capability

```php
$capability = $event->getCapability(); // ?string — e.g. "text_generation"
```

---

## Caller Attribution

The AI hooks don't tell you **which plugin** made the call. Use `debug_backtrace()` to walk the call stack and match file paths against `WP_PLUGIN_DIR`:

```php
$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 );

foreach ( $trace as $frame ) {
    if ( ! isset( $frame['file'] ) ) {
        continue;
    }
    $path = wp_normalize_path( $frame['file'] );
    if ( str_starts_with( $path, wp_normalize_path( WP_PLUGIN_DIR ) ) ) {
        // Extract plugin slug from the path segment after wp-content/plugins/
        $relative = substr( $path, strlen( wp_normalize_path( WP_PLUGIN_DIR ) ) + 1 );
        $slug     = explode( '/', $relative )[0];
        break;
    }
}
```

**Tip:** Stash the detected slug in a static property during `prevent_prompt`, then reuse it in `before_generate_result` and `after_generate_result` for correlation.

---

## Complete Logging Example

Putting it all together — a self-contained snippet that gates requests and logs token usage:

```php
add_filter( 'wp_ai_client_prevent_prompt', 'my_gate_prompt', 10, 2 );
add_action( 'wp_ai_client_after_generate_result', 'my_log_usage', 10, 1 );

function my_gate_prompt( bool $prevent, WP_AI_Client_Prompt_Builder $builder ): bool {
    if ( $prevent ) {
        return true;
    }
    // Example: block a specific plugin.
    $slug = detect_caller_plugin(); // Your backtrace logic.
    if ( 'expensive-plugin' === $slug ) {
        return true;
    }
    return false;
}

function my_log_usage( AfterGenerateResultEvent $event ): void {
    $model = $event->getModel();
    $usage = $event->getResult()->getTokenUsage();

    error_log( sprintf(
        '[AI] provider=%s model=%s capability=%s tokens=%d (prompt=%d completion=%d)',
        $model->providerMetadata()->getId(),
        $model->metadata()->getId(),
        $event->getCapability() ?? 'n/a',
        $usage->getTotalTokens(),
        $usage->getPromptTokens(),
        $usage->getCompletionTokens(),
    ) );
}
```

---

## Debugging Checklist

If hooks aren't firing as expected:

1. **Is the AI connector configured?** — `wp_supports_ai()` must return `true`.
2. **Is `wp_ai_client_prompt()` being used?** — Only this function triggers the hooks. Direct SDK usage bypasses them.
3. **Are before/after actions silent?** — Ensure you're on WP 7 RC1+. Earlier betas had a core bug where the event dispatcher wasn't injected (now fixed).
4. **Are you getting errors on token access?** — Use getter methods, not property access.
5. **Is your `prevent_prompt` filter returning the wrong type?** — Must return `bool`. Returning a non-bool can break the filter chain.
6. **Check hook registration:** `wp eval 'var_dump( has_filter("wp_ai_client_prevent_prompt") );'`
7. **Check event dispatcher exists:** `wp eval 'var_dump( WordPress\AiClient\AiClient::getEventDispatcher() );'`
