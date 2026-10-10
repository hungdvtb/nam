# Ollama Local Setup For XAMPP

This setup is for local prompt testing only. Mock remains the default provider, and Ollama is disabled until you enable it locally.

## 1. Install And Start Ollama

Install Ollama on the same machine as XAMPP, then start the local server:

```text
ollama serve
```

Pull the default model:

```text
ollama pull llama3.1:8b
```

You can use `qwen2.5:7b` instead by overriding the model in local config.

## 2. Enable Ollama Locally

Create or update `config/local.php`:

```php
<?php

return [
    'ai_providers' => [
        'providers' => [
            'ollama' => [
                'enabled' => true,
                'endpoint' => 'http://localhost:11434',
                'default_model' => 'llama3.1:8b',
            ],
        ],
    ],
];
```

`config/local.php` is ignored by git. Ollama does not need an API key.

Optional `.env.local` values are also supported:

```text
OLLAMA_ENDPOINT=http://localhost:11434
OLLAMA_MODEL_DEFAULT=llama3.1:8b
```

## 3. Check Provider Status

Open this local API endpoint in the browser:

```text
http://localhost/ai%20trade/api.php?action=ollama_provider_status
```

Expected safe fields:

- `enabled_status`: `enabled` or `disabled`
- `api_key_status`: `not_required`
- `model`: `set` or `missing_model`
- `running_status`: `running`, `not_running`, `running_model_missing`, or `disabled`
- `health_status`: provider health summary

The diagnostic response does not return an API key because Ollama does not use one.

## 4. Test In Prompt AI

1. Open the Prompt AI page.
2. Select an Active prompt.
3. Choose provider `Ollama Local`.
4. Fill sample variables.
5. Click the prompt test button.

If Ollama is not installed, not running, or missing the configured model, the UI shows a normalized provider error.

## Safety Notes

- Mock remains default in `config/ai_providers.php`.
- Ollama is opt-in through local config.
- The provider only accepts localhost, 127.0.0.1, or ::1 endpoints.
- No API key is required.
- No cloud provider call is made by the Ollama connector.
- No optimizer loop, live trading, auto trading, trading logic, or backtest calculation is changed by this setup.
