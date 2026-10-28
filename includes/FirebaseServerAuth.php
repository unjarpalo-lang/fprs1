<?php
/**
 * Minimal, dependency-free Firebase server integration for PHP.
 *
 * There is no official Firebase Admin SDK for PHP, and this project has no
 * Composer/vendor setup — so instead of pulling in a third-party package,
 * this talks to Firebase over plain REST:
 *   - ID token verification uses the public accounts:lookup REST endpoint
 *     (keyed by your Web API key — safe to expose client-side already).
 *   - Server-to-server Realtime Database access uses a service-account
 *     JSON key: we hand-sign a JWT with openssl (built into PHP) and
 *     exchange it for an OAuth2 access token, per Google's documented
 *     "JWT bearer token" flow. That access token is a full-admin
 *     credential — RTDB security rules do not apply to it.
 *
 * Get a service-account key: Firebase Console > Project settings >
 * Service accounts > Generate new private key. Keep that JSON file OUTSIDE
 * webroot or add it to .gitignore; never expose it to the browser.
 */
class FirebaseServerAuth
{
    private string $webApiKey;
    private string $databaseUrl;
    private array $serviceAccount;
    private ?string $cachedAccessToken = null;
    private int $cachedAccessTokenExpiry = 0;

    public function __construct(string $webApiKey, string $databaseUrl, string $serviceAccountJsonPath)
    {
        $this->webApiKey = $webApiKey;
        $this->databaseUrl = rtrim($databaseUrl, '/');
        $json = file_get_contents($serviceAccountJsonPath);
        if ($json === false) {
            throw new RuntimeException("Could not read service account file: {$serviceAccountJsonPath}");
        }
        $this->serviceAccount = json_decode($json, true);
        if (!isset($this->serviceAccount['private_key'], $this->serviceAccount['client_email'])) {
            throw new RuntimeException('Service account JSON is missing private_key/client_email.');
        }
    }

    /**
     * Verify a client-supplied Firebase ID token and return the decoded
     * account info, or null if the token is invalid/expired.
     * @return array{uid:string,email:?string,emailVerified:bool}|null
     */
    public function verifyIdToken(string $idToken): ?array
    {
        $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode($this->webApiKey);
        $response = $this->httpPostJson($url, ['idToken' => $idToken]);
        if ($response === null || empty($response['users'][0]['localId'])) {
            return null;
        }
        $account = $response['users'][0];
        return [
            'uid' => $account['localId'],
            'email' => $account['email'] ?? null,
            'emailVerified' => (bool)($account['emailVerified'] ?? false),
        ];
    }

    /** Read a Realtime Database path (server credential — bypasses security rules). Returns null if the path doesn't exist. */
    public function rtdbGet(string $path): mixed
    {
        $url = $this->databaseUrl . '/' . ltrim($path, '/') . '.json';
        $raw = $this->httpRequest($url, 'GET', null, 'application/json', true, true);
        return $raw === null ? null : json_decode($raw, true);
    }

    /** Overwrite a Realtime Database path (server credential — bypasses security rules). */
    public function rtdbSet(string $path, mixed $value): void
    {
        $url = $this->databaseUrl . '/' . ltrim($path, '/') . '.json';
        $this->httpRequest($url, 'PUT', $value, 'application/json', false, true);
    }

    /** Shallow-merge fields into a Realtime Database path. */
    public function rtdbUpdate(string $path, array $value): void
    {
        $url = $this->databaseUrl . '/' . ltrim($path, '/') . '.json';
        $this->httpRequest($url, 'PATCH', $value, 'application/json', false, true);
    }

    private function getAccessToken(): string
    {
        if ($this->cachedAccessToken && time() < $this->cachedAccessTokenExpiry - 30) {
            return $this->cachedAccessToken;
        }
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/userinfo.email',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = "{$header}.{$claims}";
        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $this->serviceAccount['private_key'], 'sha256WithRSAEncryption');
        if (!$ok) {
            throw new RuntimeException('Failed to sign Firebase service-account JWT.');
        }
        $jwt = $unsigned . '.' . $this->base64UrlEncode($signature);

        $tokenResponse = $this->httpPostJson('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ], 'application/x-www-form-urlencoded');
        if (empty($tokenResponse['access_token'])) {
            throw new RuntimeException('Failed to obtain Firebase access token: ' . json_encode($tokenResponse));
        }
        $this->cachedAccessToken = $tokenResponse['access_token'];
        $this->cachedAccessTokenExpiry = $now + (int)($tokenResponse['expires_in'] ?? 3600);
        return $this->cachedAccessToken;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function httpPostJson(string $url, array $body, string $contentType = 'application/json'): ?array
    {
        $payload = $contentType === 'application/json' ? json_encode($body) : http_build_query($body);
        $raw = $this->httpRequest($url, 'POST', $payload, $contentType, true);
        return $raw === null ? null : json_decode($raw, true);
    }

    /**
     * @param bool $rawBody Send $body as-is (already-encoded string) instead of JSON-encoding it.
     * @param bool $bearerAuth Attach "Authorization: Bearer <service-account access token>" —
     *   used for Realtime Database calls. Never put the token in the URL: query strings end up
     *   in server logs, proxies, and error messages, unlike a header.
     * @return string|null Response body, or null on 404 (treated as "no data at this path").
     */
    private function httpRequest(string $url, string $method, mixed $body, string $contentType = 'application/json', bool $rawBody = false, bool $bearerAuth = false): ?string
    {
        $headers = ["Content-Type: {$contentType}"];
        if ($bearerAuth) {
            $headers[] = 'Authorization: Bearer ' . $this->getAccessToken();
        }
        $ch = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ];
        if ($method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = $rawBody ? $body : json_encode($body);
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Firebase HTTP request failed: {$error}");
        }
        if ($statusCode === 404) {
            return null;
        }
        if ($statusCode >= 400) {
            throw new RuntimeException("Firebase request to {$method} returned HTTP {$statusCode}: " . substr($response, 0, 300));
        }
        return $response;
    }
}
