<?php

namespace App\Services;

use App\Models\LegalBrief;
use App\Models\BriefCitation;

class BriefExportService
{
    public function __construct(
        private readonly GoogleDocsExportService $googleDocs,
        private readonly DocxGenerator $docxGenerator,
    ) {}

    public function export(LegalBrief $brief, string $format): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $brief->loadMissing('citations');
        $filename = $this->safeFilename($brief->title ?: 'brief');
        $html = $this->buildHtml($brief, $format === 'court_filing');

        return match ($format) {
            'pdf' => $this->pdfResponse($html, $filename),
            'word' => $this->docxResponse($html, $filename),
            'court_filing' => $this->courtFilingResponse($html, $filename),
            'google_docs' => $this->googleDocs->export($brief, $html, $filename),
            default => response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.html"',
            ]),
        };
    }

    protected function buildHtml(LegalBrief $brief, bool $courtFiling): string
    {
        $courtClass = $courtFiling ? ' court-filing' : '';
        $citations = $brief->citations
            ->map(fn (BriefCitation $citation) => '<li><strong>'.e($citation->authority).':</strong> '
                .e($citation->citation_text)
                .($citation->source_note ? ' — '.e($citation->source_note) : '')
                .'</li>')
            ->join('');

        $courtRules = $courtFiling
            ? '<p class="meta"><em>Certificate of service and caption blocks must be completed per local rules before filing.</em></p>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'
            .e($brief->title)
            .'</title><style>body{font-family:"Times New Roman",serif;margin:1in;line-height:1.5;font-size:12pt}'
            .'.court-filing{font-size:12pt}.meta{color:#444;font-size:11pt;margin-bottom:1em}'
            .'h1{text-align:center}h2{margin-top:1.5em}</style></head><body class="'
            .$courtClass
            .'"><div class="meta"><p><strong>Brief type:</strong> '
            .e(str_replace('_', ' ', $brief->brief_type))
            .'</p>'
            .($brief->jurisdiction ? '<p><strong>Jurisdiction:</strong> '.e($brief->jurisdiction).'</p>' : '')
            .($brief->court_type ? '<p><strong>Court:</strong> '.e(str_replace('_', ' ', $brief->court_type)).'</p>' : '')
            .'</div>'
            .$courtRules
            .'<h1>'
            .e($brief->title)
            .'</h1>'
            .($brief->content_html ?? '<p></p>')
            .($citations !== '' ? '<h2>Authorities cited</h2><ol>'.$citations.'</ol>' : '')
            .'</body></html>';
    }

    protected function pdfResponse(string $html, string $filename): \Symfony\Component\HttpFoundation\Response
    {
        $wrapped = str_replace(
            '"Times New Roman",serif',
            'DejaVu Serif, serif',
            $html,
        );

        if (! class_exists(\Dompdf\Dompdf::class)) {
            abort(503, 'PDF export requires dompdf/dompdf. Run composer install in the backend.');
        }

        $dompdf = new \Dompdf\Dompdf;
        $dompdf->loadHtml($wrapped);
        $dompdf->setPaper('letter');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
        ]);
    }

    protected function docxResponse(string $html, string $filename): \Symfony\Component\HttpFoundation\Response
    {
        return response($this->docxGenerator->fromHtml($html), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.docx"',
        ]);
    }

    protected function courtFilingResponse(string $html, string $filename): \Symfony\Component\HttpFoundation\Response
    {
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'-court-filing.html"',
        ]);
    }

    protected function safeFilename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'brief';
    }
}
