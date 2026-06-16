<?php

namespace App\Services;

use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class OnlyOfficeConfigService
{
    public function isConfigured(): bool
    {
        $url = config('onlyoffice.url');

        return is_string($url) && $url !== '';
    }

    public function isWordMime(?string $mimeType): bool
    {
        if (! is_string($mimeType) || $mimeType === '') {
            return false;
        }

        return in_array($mimeType, config('onlyoffice.word_mime_types', []), true);
    }

    public function canEditInOnlyOffice(LegalDocument $document): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        if (! $this->isWordMime($document->mime_type)) {
            return false;
        }

        if ($document->content_html) {
            return false;
        }

        return is_string($document->path) && $document->path !== '';
    }

    public function documentKey(LegalDocument $document): string
    {
        $version = (int) ($document->version ?? 1);
        $stamp = $document->updated_at?->timestamp ?? time();

        return substr(hash('sha256', "{$document->id}:{$version}:{$stamp}"), 0, 20);
    }

    public function signedFileUrl(LegalDocument $document): string
    {
        $ttlMinutes = max(1, (int) config('onlyoffice.file_url_ttl', 120));

        return URL::temporarySignedRoute(
            'onlyoffice.file',
            now()->addMinutes($ttlMinutes),
            ['document' => $document->id],
        );
    }

    public function callbackUrl(LegalDocument $document): string
    {
        return url("/api/v1/documents/{$document->id}/onlyoffice-callback");
    }

    public function editorScriptUrl(): string
    {
        return rtrim((string) config('onlyoffice.url'), '/').'/web-apps/apps/api/documents/api.js';
    }

    public function documentServerUrl(): string
    {
        return rtrim((string) config('onlyoffice.url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEditorConfig(LegalDocument $document, User $user): array
    {
        $filename = $document->original_filename ?? $document->name;
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'docx');
        $fileType = $extension === 'doc' ? 'doc' : 'docx';

        $config = [
            'document' => [
                'fileType' => $fileType,
                'key' => $this->documentKey($document),
                'title' => $document->name,
                'url' => $this->signedFileUrl($document),
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'callbackUrl' => $this->callbackUrl($document),
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => [
                    'compactHeader' => true,
                ],
            ],
        ];

        return $this->signConfig($config);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function signConfig(array $config): array
    {
        $secret = config('onlyoffice.jwt_secret');
        if (! is_string($secret) || $secret === '') {
            return $config;
        }

        $config['token'] = $this->encodeJwt($config, $secret);

        return $config;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeToken(string $token): ?array
    {
        $secret = config('onlyoffice.jwt_secret');
        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true)
        );

        if (! hash_equals($expected, $signatureB64)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encodeJwt(array $payload, string $secret): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveCallbackPayload(array $body): array
    {
        if (isset($body['token']) && is_string($body['token'])) {
            $decoded = $this->decodeToken($body['token']);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $body;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : '';
    }
}
