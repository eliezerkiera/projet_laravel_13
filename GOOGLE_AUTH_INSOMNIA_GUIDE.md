# Google OAuth Testing Guide for Insomnia

This guide explains how to test the Google OAuth authentication endpoints using Insomnia.

## Prerequisites

1. **Import OpenAPI Spec**: Import the `openapi.yaml` file into Insomnia to automatically generate the request collection.
2. **Google Cloud Setup**: You need a Google Cloud project with OAuth 2.0 credentials configured (see Google Cloud Console section below).

## Importing the OpenAPI Spec

1. Open Insomnia
2. Click **Application** > **Import/Export** > **Import Data**
3. Select **From File** and choose `openapi.yaml`
4. Insomnia will automatically create a collection with all API endpoints including the new Google OAuth endpoints

## Google Cloud Console Configuration

### 1. Create OAuth 2.0 Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Navigate to **APIs & Services** > **Credentials**
4. Click **Create Credentials** > **OAuth client ID**
5. Select **Web application** as the application type
6. Configure **Authorized redirect URIs**:
   - For callback route: `http://localhost:8000/api/v2/auth/google/callback`
   - Add additional URIs for production environments
7. Copy the **Client ID** and **Client Secret**

### 2. Configure OAuth Consent Screen

1. In Google Cloud Console, go to **OAuth consent screen**
2. Choose **External** or **Internal** based on your needs
3. Fill in the required fields:
   - App name
   - User support email
   - Developer contact information
4. Add the required scopes:
   - `openid`
   - `email`
   - `profile`
5. Save and submit for verification (not required for testing)

### 3. Configure Environment Variables

Add the following to your `.env` file:

```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v2/auth/google/callback
```

## Testing Endpoints in Insomnia

### Flow A: Redirection (Web)

This flow is used for web applications that redirect users to Google's login page.

#### Step 1: Get Redirect URL

1. In Insomnia, select the `GET /auth/google/redirect` request
2. Send the request
3. Copy the `redirect_url` from the response
4. Open this URL in a browser
5. Authorize the application on Google's consent screen
6. Google will redirect to your callback URL with an authorization code

#### Step 2: Handle Callback

1. After authorization, Google redirects to: `http://localhost:8000/api/v2/auth/google/callback?code=...`
2. The API will automatically handle the callback and return the tokens
3. Copy the `access_token` from the response

### Flow B: Token (SPA/Mobile)

This flow is used for SPA or mobile applications that handle OAuth on the client side.

#### Step 1: Get Google Access Token

Use your client-side application (React, Vue, mobile app) to:
1. Implement Google Sign-In using Google's client SDK
2. Obtain the Google access token after user authorization
3. Send this token to your backend API

#### Step 2: Authenticate with Backend

1. In Insomnia, select the `POST /auth/google/token` request
2. Set the request body:
   ```json
   {
     "token": "ya29.a0AfH6SMB...",
     "device_name": "My iPhone"
   }
   ```
3. Replace `token` with the actual Google access token from your client
4. Send the request
5. Copy the `access_token` and `refresh_token` from the response

## Using the Sanctum Token

Once you have the `access_token`, you can access protected endpoints:

1. Select any protected endpoint (e.g., `GET /auth/me`)
2. Go to the **Auth** tab
3. Select **Bearer Token**
4. Paste the `access_token` in the token field
5. Send the request

Example cURL:

```bash
curl -X GET http://localhost:8000/api/v2/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Test Scenarios

### Scenario 1: New User Registration

1. Use a Google account that has never been used with your app
2. Call `POST /auth/google/token` with a valid Google token
3. Expected: New user created, social account linked, tokens returned
4. Verify in database: `users` table has new record, `social_accounts` table has Google link

### Scenario 2: Link to Existing User

1. Create a user via registration with email `test@example.com`
2. Use a Google account with the same email `test@example.com`
3. Call `POST /auth/google/token` with Google token
4. Expected: Google account linked to existing user, no duplicate user created
5. Verify in database: One user record, one `social_accounts` entry

### Scenario 3: Already Linked Account

1. Login with Google once (creates social account link)
2. Call `POST /auth/google/token` again with same Google token
3. Expected: Direct login, no new user or social account created
4. Verify: Same user logged in, no database changes

### Scenario 4: Invalid Token

1. Call `POST /auth/google/token` with an invalid/expired token
2. Expected: `422` error with code `google_token_invalid`

### Scenario 5: Unverified Email

1. Create a user with unverified Google email
2. Call `POST /auth/google/token` with that Google account
3. Expected: `422` error with code `google_email_not_verified`

## Refresh Token Usage

After access token expires, use the refresh token:

1. Select `POST /auth/refresh` request
2. Set request body:
   ```json
   {
     "refresh_token": "YOUR_REFRESH_TOKEN"
   }
   ```
3. Send the request
4. Receive new `access_token` and `refresh_token`

## Environment Configuration in Insomnia

Create different environments in Insomnia:

### Development
```
BASE_URL=http://localhost:8000/api/v2
```

### Production
```
BASE_URL=https://your-api-domain.com/api/v2
```

## Troubleshooting

### Error: `google_token_invalid`
- Ensure the Google token is valid and not expired
- Check that the token was obtained from the correct Google project
- Verify the token format (should start with `ya29.`)

### Error: `google_email_not_verified`
- The Google account email is not verified
- User must verify their email in Google account settings

### Error: `google_auth_failed`
- Check Google Cloud Console configuration
- Verify redirect URI matches exactly (including protocol and port)
- Ensure OAuth consent screen is properly configured

### Redirect URI Mismatch
- Ensure `GOOGLE_REDIRECT_URI` in `.env` matches Google Cloud Console exactly
- Include protocol (`http://` or `https://`) and port (`:8000`)
- No trailing slashes

## Security Notes

- Never commit real `GOOGLE_CLIENT_ID` or `GOOGLE_CLIENT_SECRET` to version control
- Use different credentials for development and production
- Regularly rotate your Google OAuth secrets
- Monitor Google Cloud Console for unauthorized usage
- Use HTTPS in production for all OAuth redirects
