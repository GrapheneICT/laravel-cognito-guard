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

### SPA with Hosted UI (authorization code + PKCE) — end-to-end recipe

The full, recommended flow for a browser SPA talking to a Laravel API behind this guard. **Use `authorization_code` with PKCE, not the legacy implicit (`response_type=token`) flow.**

**1. Cognito-side setup**

In your App Client → **App integration**:

- **Hosted UI** → assign a domain (e.g. `auth.example.com` or `<prefix>.auth.<region>.amazoncognito.com`).
- **Allowed callback URLs:** `https://app.example.com/auth/callback` (the SPA route that exchanges the code).
- **Allowed sign-out URLs:** `https://app.example.com/`.
- **OAuth grant types:** check **Authorization code grant**. Leave Implicit grant unchecked.
- **OAuth scopes:** at minimum `openid`, plus `email` / `profile` if you need them, plus any custom resource-server scopes.

**2. SPA — start the login**

When the user clicks "Sign in":

```js
// Generate PKCE verifier + challenge
const verifier = base64url(crypto.getRandomValues(new Uint8Array(32)))
const challenge = base64url(
  new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier)))
)
sessionStorage.setItem('pkce_verifier', verifier)

const params = new URLSearchParams({
  client_id: 'YOUR_APP_CLIENT_ID',
  response_type: 'code',
  scope: 'openid email',
  redirect_uri: 'https://app.example.com/auth/callback',
  code_challenge: challenge,
  code_challenge_method: 'S256',
})

window.location.assign(`https://YOUR_DOMAIN/oauth2/authorize?${params}`)
```

**3. SPA — handle the callback and exchange the code**

At `/auth/callback`:

```js
const code = new URL(window.location.href).searchParams.get('code')
const verifier = sessionStorage.getItem('pkce_verifier')

const res = await fetch('https://YOUR_DOMAIN/oauth2/token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    grant_type: 'authorization_code',
    client_id: 'YOUR_APP_CLIENT_ID',
    code,
    redirect_uri: 'https://app.example.com/auth/callback',
    code_verifier: verifier,
  }),
})

const { access_token, id_token, refresh_token, expires_in } = await res.json()
// Store access_token in memory (preferred) or sessionStorage.
// Store refresh_token only if you must — and never in localStorage in plain form.
```

**4. SPA — call the Laravel API**

```js
await fetch('https://api.example.com/me', {
  headers: { Authorization: `Bearer ${access_token}` },
})
```

**5. Laravel — validate**

The route is just:

```php
Route::middleware('auth:cognito')->get('/me', fn () => auth()->user());
```

This package handles everything else: signature against JWKS, issuer, `token_use`, `client_id` allow-list, scopes, expiry. If the token is bad, Laravel returns 401.

**6. Refresh**

When `access_token` is near expiry (use `expires_in` to schedule), POST to `/oauth2/token` again with `grant_type=refresh_token` and the stored refresh token. The Laravel side stays untouched.

**Common gotchas**

- **`redirect_uri` mismatch.** Must be byte-for-byte identical between the `/authorize` call, `/token` call, and the App Client's allowed callback list. Trailing slash counts.
- **Mixed token types.** If your SPA accidentally sends the `id_token` instead of the `access_token`, the guard accepts both by default — but `client_id` vs `aud` allow-list semantics differ. Restrict via `cognito-guard.pools.<name>.allowed_token_use` to lock this down.
- **CORS on `/oauth2/token`.** Cognito's token endpoint accepts cross-origin POSTs from your SPA without preflight as long as you stick to `Content-Type: application/x-www-form-urlencoded` (a CORS-safelisted header). If you switch to JSON, you'll trigger preflight and need an OPTIONS handler Cognito doesn't provide.
- **Refresh-token rotation isn't on by default.** Enable it in the App Client if you store refresh tokens client-side.
- **`scope` claim is missing from id tokens.** If you've set `required_scopes` and configured `allowed_token_use: ['id']`, every request will 401. Either widen `allowed_token_use` to `['access']` or drop `required_scopes`.

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
