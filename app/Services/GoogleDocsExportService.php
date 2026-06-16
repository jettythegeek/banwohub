<?php

namespace App\Services;

use App\Models\LegalBrief;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class GoogleDocsExportService
{
    public function __construct(
        private readonly DocxGenerator $docxGenerator,
    ) {}

    /**
     * @return Response|JsonResponse
     */
    public function export(LegalBrief $brief, string $html, string $filename): Response|JsonResponse
    {
        $clientId = (string) config('services.google_docs.client_id', '');
        $clientSecret = (string) config('services.google_docs.client_secret', '');
        $refreshToken = (string) config('services.google_docs.refresh_token', '');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return response()->json([
                'message' => 'Google Docs direct export requires OAuth credentials. Download the DOCX file and upload to Google Drive, or configure GOOGLE_DOCS_* environment variables.',
                'content_html' => $html,
                'download_format' => 'docx',
                'export_url_hint' => 'https://docs.google.com/document/create',
                'setup' => [
                    'GOOGLE_DOCS_CLIENT_ID' => 'Google Cloud OAuth client ID with Drive scope',
                    'GOOGLE_DOCS_CLIENT_SECRET' => 'OAuth client secret',
                    'GOOGLE_DOCS_REFRESH_TOKEN' => 'Refresh token with https://www.googleapis.com/auth/drive.file scope',
                ],
            ]);
        }

        $accessToken = $this->refreshAccessToken($clientId, $clientSecret, $refreshToken);
        if ($accessToken === null) {
            return response()->json([
                'message' => 'Google OAuth token refresh failed. Re-authorize Google Docs integration.',
                'content_html' => $html,
            ], 502);
        }

        $docxBinary = $this->docxGenerator->fromHtml($html);
        $upload = $this->uploadToDrive($accessToken, $docxBinary, $filename.'.docx');

        if ($upload === null) {
            return response()->json([
                'message' => 'Google Drive upload failed.',
                'content_html' => $html,
            ], 502);
        }

        return response()->json([
            'message' => 'Brief uploaded to Google Drive. Open the document to edit in Google Docs.',
            'document_id' => $upload['id'],
            'export_url_hint' => $upload['webViewLink'] ?? 'https://docs.google.com/document/d/'.$upload['id'].'/edit',
            'content_html' => $html,
        ]);
    }

    protected function refreshAccessToken(string $clientId, string $clientSecret, string $refreshToken): ?string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            return null;
        }

        return (string) ($response->json('access_token') ?? '');
    }

    /**
     * @return array{id: string, webViewLink?: string}|null
     */
    protected function uploadToDrive(string $accessToken, string $binary, string $filename): ?array
    {
        $metadata = json_encode([
            'name' => $filename,
            'mimeType' => 'application/vnd.google-apps.document',
        ]);

        $boundary = 'banwohub_'.bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadata."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document\r\n\r\n"
            .$binary."\r\n"
            ."--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'multipart/related; boundary='.$boundary])
            ->withBody($body, 'multipart/related; boundary='.$boundary)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink');

        if (! $response->successful()) {
            return null;
        }

        /** @var array{id?: string, webViewLink?: string} $data */
        $data = $response->json();

        return isset($data['id']) ? $data : null;
    }
}
