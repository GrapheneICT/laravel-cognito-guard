# Setting up AWS Cognito for this guard

This page covers the AWS side of the wiring. If you already have a working Cognito User Pool, skip to [step 4](#step-4--client-app-token-flow).

## Step 1 — create the User Pool

### Via AWS Console

1. Open the Cognito console → **User Pools** → **Create user pool**.
2. **Cognito user pool sign-in options:** pick the identifiers your app will accept (typically `Email`).
3. **Password policy / MFA:** your choice. Doesn't affect this package.
4. **Sign-up experience → Required attributes:** include at least `email`. Add any custom attributes you want surfaced as JWT claims.
5. **Message delivery:** pick "Send email with Cognito" for development, SES for production.
6. **Integrate your app:**
   - **User pool name:** e.g. `my-app-users`.
   - **App type:** **Public client** for SPAs and mobile apps; **Confidential client** for server-to-server.
   - **App client name:** e.g. `my-app-web`.
   - **Authentication flows:** for SPAs, enable `ALLOW_USER_SRP_AUTH` + `ALLOW_REFRESH_TOKEN_AUTH`. For server-side flows, also enable `ALLOW_ADMIN_USER_PASSWORD_AUTH`.
   - **Hosted UI** (optional): enable if you want Cognito to host login/signup pages. Set callback URLs to your app.
7. Review → **Create user pool**.

After creation, note these values for `.env`:

- **User pool ID** (top of the pool page) → `COGNITO_USER_POOL_ID`
- **Region** (URL, e.g. `us-east-1`) → `AWS_REGION`
- **App client ID** (under **App integration → App clients**) → `COGNITO_CLIENT_IDS`

### Via Terraform

```hcl
resource "aws_cognito_user_pool" "main" {
  name = "my-app-users"

  auto_verified_attributes = ["email"]

  password_policy {
    minimum_length    = 12
    require_lowercase = true
    require_uppercase = true
    require_numbers   = true
    require_symbols   = false
  }

  schema {
    name                = "email"
    attribute_data_type = "String"
    required            = true
    mutable             = true
  }
}

resource "aws_cognito_user_pool_client" "web" {
  name         = "my-app-web"
  user_pool_id = aws_cognito_user_pool.main.id

  generate_secret = false # public client (SPA)

  explicit_auth_flows = [
    "ALLOW_USER_SRP_AUTH",
    "ALLOW_REFRESH_TOKEN_AUTH",
  ]

  prevent_user_existence_errors = "ENABLED"
}

output "cognito_user_pool_id" {
  value = aws_cognito_user_pool.main.id
}

output "cognito_client_id" {
  value = aws_cognito_user_pool_client.web.id
}
```

## Step 2 — choose access tokens vs. id tokens

Cognito issues three token types: **id token**, **access token**, **refresh token**.

| Token | Carries | Use it when |
|---|---|---|
| **access token** | `sub`, `client_id`, `scope`, `cognito:groups`, `username` | API-style auth (most cases). This is the default for this guard. |
| **id token** | `sub`, `aud`, `email`, custom user attributes | SPA flows that need user profile data right after login. |
| **refresh token** | nothing parseable | Out of scope for this guard; clients exchange refresh tokens for new access tokens via Cognito. |

Cognito access tokens are stripped down by default. If you need claims like `email` on the access token, configure the App Client → **Token customization** → **Access token customization** to include attribute scopes.

This guard accepts both `access` and `id` tokens by default. Tighten to one via `cognito-guard.pools.<name>.allowed_token_use`.

## Step 3 — groups → Gates

In the User Pool → **Groups** tab → **Create group**, add e.g. `admins`, `editors`. Assign users to groups.

Tokens minted for those users will include `"cognito:groups": ["admins", ...]`. With the default `cognito-guard.bridge_groups_to_gates = true`, those group names automatically resolve as Laravel Gate abilities — `Gate::allows('admins')` works with no further wiring.

## Step 4 — client app token flow

This guard only **verifies** tokens; your client app is responsible for **obtaining** them. The two common patterns:

### SPA with Hosted UI

1. Redirect the user to `https://<your-pool-domain>/login?...client_id=...&response_type=token&scope=openid+email&redirect_uri=...`.
2. Cognito redirects back with `#access_token=...&id_token=...` in the URL fragment.
3. Your SPA stores the access token and sends it as `Authorization: Bearer <jwt>` on API calls to your Laravel app.

### Direct SRP (no Hosted UI)

Use the Cognito SDK in your SPA (`aws-amplify` for JS) to perform `signIn(username, password)` → receive tokens → send `Authorization: Bearer <access_token>`.

### Server-to-server

For machine clients: create a **Confidential client** in Cognito, use the **client_credentials** grant against your pool domain's `/oauth2/token` endpoint, get back an access token, send it on requests. Configure your resource server scopes on the App Client.

## Step 5 — point this guard at the pool

```dotenv
COGNITO_USER_POOL_ID=us-east-1_XXXXXXXXX
AWS_REGION=us-east-1
COGNITO_CLIENT_IDS=abc123-the-web-client-id
```

Then in `config/auth.php`:

```php
'guards' => [
    'cognito' => ['driver' => 'cognito', 'provider' => 'cognito', 'pool' => 'default'],
],
'providers' => [
    'cognito' => ['driver' => 'cognito'],
],
```

## Step 6 — test the wiring

```bash
php artisan about
# Look for the "Cognito Guard" section listing your pool ID.

php artisan cognito:test-token "$(cat real-access-token.txt)"
# Parses the token, validates against the configured pool, prints diagnosis.
```

If `cognito:test-token` reports `Token is valid`, the guard is wired correctly. If it fails, the output tells you which check (signature / issuer / token_use / client_id / scope / expiry) tripped.
