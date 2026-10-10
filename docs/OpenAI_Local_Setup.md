# OpenAI Local Setup For XAMPP

This setup is for local prompt testing only. Mock remains the default provider, and no OpenAI key should be committed to source control.

## 1. Create `.env.local`

Copy `.env.example` to `.env.local` in the project root:

```text
OPENAI_API_KEY=
OPENAI_MODEL_DEFAULT=
```

Fill the values locally. Do not put quotes around the key unless the value needs them.

`.env.local` is ignored by git.

## 2. Enable OpenAI Chat Locally

Create `config/local.php`:

```php
<?php

return [
    'ai_providers' => [
        'providers' => [
            'openai_chat' => [
                'enabled' => true,
            ],
        ],
    ],
];
```

`config/local.php` is ignored by git. It should contain local toggles only, not API keys.

## 3. Check Provider Status

Open this local API endpoint in the browser:

```text
http://localhost/ai%20trade/api.php?action=openai_provider_status
```

Expected safe fields:

- `enabled_status`: `enabled` or `disabled`
- `configured`: `configured` or `missing_api_key`
- `model`: `set` or `missing_model`
- `health_status`: provider health summary

The diagnostic response never returns the API key.

## 4. Test In Prompt AI

1. Open the Prompt AI page.
2. Select an Active prompt.
3. Choose provider `OpenAI Chat`.
4. Fill sample variables.
5. Click the prompt test button.

If OpenAI is disabled or missing config, the UI shows a normalized configuration error.

## Safety Notes

- Mock remains default in `config/ai_providers.php`.
- OpenAI Chat is opt-in through local config.
- No Codex provider is enabled.
- No optimizer loop, live trading, auto trading, trading logic, or backtest calculation is changed by this setup.
