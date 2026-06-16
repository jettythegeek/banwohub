<?php

namespace App\Services;

use ZipArchive;

class DocxGenerator
{
    public function fromHtml(string $html): string
    {
        if (class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            return $this->viaPhpWord($html);
        }

        return $this->minimalDocx($html);
    }

    protected function viaPhpWord(string $html): string
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord;
        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $html, false, false);

        $temp = tempnam(sys_get_temp_dir(), 'brief_');
        $path = $temp.'.docx';
        @unlink($temp);
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);
        $binary = file_get_contents($path) ?: '';
        @unlink($path);

        return $binary;
    }

    protected function minimalDocx(string $html): string
    {
        $bodyText = $this->htmlToPlainParagraphs($html);
        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .$bodyText
            .'<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
            .'</w:body></w:document>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';

        $wordRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>';

        $temp = tempnam(sys_get_temp_dir(), 'docx_');
        $zipPath = $temp.'.docx';
        @unlink($temp);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->addFromString('word/_rels/document.xml.rels', $wordRels);
        $zip->close();

        $binary = file_get_contents($zipPath) ?: '';
        @unlink($zipPath);

        return $binary;
    }

    protected function htmlToPlainParagraphs(string $html): string
    {
        $text = strip_tags(str_replace(['</p>', '</h1>', '</h2>', '</h3>', '<br>', '<br/>', '<br />'], "\n", $html));
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        $paragraphs = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $escaped = htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $paragraphs .= '<w:p><w:r><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>';
        }

        return $paragraphs !== '' ? $paragraphs : '<w:p><w:r><w:t></w:t></w:r></w:p>';
    }
}
