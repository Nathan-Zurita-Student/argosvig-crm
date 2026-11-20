<?php

namespace Webkul\HumanResources\Services\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Reads a file attached to the assistant on the server and keeps what the AI needs.
 *
 * Spreadsheets become a table (all rows stay here, only the headers and a sample go to the
 * AI), text documents become text, and images or scanned PDFs are sent as media. The result
 * is cached for an hour so follow-up requests in the same conversation reuse it.
 */
class AssistantFiles
{
    public const EXTENSIONS = ['pdf', 'docx', 'xlsx', 'xls', 'csv', 'txt', 'md', 'png', 'jpg', 'jpeg', 'webp'];

    public const MAX_KILOBYTES = 10240;

    public const MAX_ROWS = 2000;

    private const MAX_COLUMNS = 40;

    private const MAX_TEXT = 60000;

    /**
     * Characters of a document sent to the AI, about eight thousand tokens.
     */
    private const PROMPT_TEXT = 24000;

    private const SAMPLE_ROWS = 15;

    private const TTL_MINUTES = 60;

    public function store(UploadedFile $upload, int $userId): array
    {
        $extension = strtolower($upload->getClientOriginalExtension());

        $file = [
            'name' => Str::limit($upload->getClientOriginalName(), 120, ''),
            'extension' => $extension,
            ...$this->read($upload->getRealPath(), $extension),
        ];

        $token = (string) Str::uuid();

        Cache::put($this->key($userId, $token), $file, now()->addMinutes(self::TTL_MINUTES));

        return [$token, $file];
    }

    public function find(?string $token, int $userId): ?array
    {
        return $token && Str::isUuid($token) ? Cache::get($this->key($userId, $token)) : null;
    }

    /**
     * What the AI receives about the file: headers and a sample for tables, the beginning of
     * the text for documents. Media goes separately, as an image or PDF part.
     */
    public function prompt(array $file): array
    {
        $summary = ['nome' => $file['name'], 'tipo' => $file['kind']];

        if ($file['kind'] === 'table') {
            return $summary + [
                'colunas' => $file['headers'],
                'total_de_linhas' => count($file['rows']),
                'linhas_de_exemplo' => array_slice($file['rows'], 0, self::SAMPLE_ROWS),
            ];
        }

        if ($file['kind'] === 'text') {
            return $summary + [
                'texto' => mb_substr($file['text'], 0, self::PROMPT_TEXT),
                'texto_cortado' => mb_strlen($file['text']) > self::PROMPT_TEXT,
            ];
        }

        return $summary + ['observacao' => 'O arquivo segue anexado como imagem ou PDF.'];
    }

    public function media(array $file): ?array
    {
        return $file['kind'] === 'media' ? ['mime' => $file['mime'], 'data' => $file['data']] : null;
    }

    private function read(string $path, string $extension): array
    {
        return match ($extension) {
            'csv' => $this->table($this->csv($path)),
            'xlsx', 'xls' => $this->table($this->spreadsheet($path)),
            'docx' => $this->text($this->docx($path)),
            'txt', 'md' => $this->text($this->utf8((string) file_get_contents($path))),
            'pdf' => $this->pdf($path),
            default => $this->mediaFile($path, 'image/'.($extension === 'jpg' ? 'jpeg' : $extension)),
        };
    }

    private function csv(string $path): array
    {
        $content = $this->utf8((string) file_get_contents($path));
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $firstLine = strtok($content, "\n") ?: '';

        // The separator used most in the header line: Brazilian files usually use ";".
        $delimiter = collect([';', ',', "\t", '|'])->sortByDesc(fn ($candidate) => substr_count($firstLine, $candidate))->first();

        $stream = fopen('php://memory', 'w+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];

        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false && count($rows) <= self::MAX_ROWS) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    private function spreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        // The first sheet with content, as it is shown (dates and numbers formatted).
        foreach ($book->getAllSheets() as $sheet) {
            $rows = $sheet->rangeToArray($sheet->calculateWorksheetDimension(), null, true, true, false);

            if (collect($rows)->flatten()->filter(fn ($cell) => filled($cell))->isNotEmpty()) {
                $book->disconnectWorksheets();

                return array_slice($rows, 0, self::MAX_ROWS + 1);
            }
        }

        $book->disconnectWorksheets();

        return [];
    }

    private function docx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            $this->unreadable();
        }

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        $xml = str_replace(['</w:p>', '<w:tab/>', '<w:br/>'], ["\n", "\t", "\n"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function pdf(string $path): array
    {
        try {
            $text = (new PdfParser)->parseFile($path)->getText();
        } catch (\Throwable) {
            $text = '';
        }

        // A scanned PDF has no text layer: the AI reads the pages themselves.
        return mb_strlen(trim($text)) >= 40 ? $this->text($text) : $this->mediaFile($path, 'application/pdf');
    }

    private function table(array $rows): array
    {
        $rows = array_values(array_filter(
            array_map(fn ($row) => array_map(fn ($cell) => trim((string) $cell), array_slice((array) $row, 0, self::MAX_COLUMNS)), $rows),
            fn ($row) => collect($row)->filter(fn ($cell) => $cell !== '')->isNotEmpty(),
        ));

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => trans('human_resources::ai.files.empty_table')]);
        }

        $headers = array_map(fn ($header, $index) => $header !== '' ? $header : 'Coluna '.($index + 1), $rows[0], array_keys($rows[0]));

        return [
            'kind' => 'table',
            'headers' => $headers,
            'rows' => array_map(fn ($row) => array_pad($row, count($headers), ''), array_slice($rows, 1, self::MAX_ROWS)),
            'truncated' => count($rows) - 1 > self::MAX_ROWS,
        ];
    }

    private function text(string $text): array
    {
        $text = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $text)));

        if ($text === '') {
            $this->unreadable();
        }

        return ['kind' => 'text', 'text' => mb_substr($text, 0, self::MAX_TEXT)];
    }

    private function mediaFile(string $path, string $mime): array
    {
        return ['kind' => 'media', 'mime' => $mime, 'data' => base64_encode((string) file_get_contents($path))];
    }

    private function utf8(string $content): string
    {
        return mb_check_encoding($content, 'UTF-8') ? $content : mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }

    private function key(int $userId, string $token): string
    {
        return 'crm-ai-file:'.$userId.':'.$token;
    }

    private function unreadable(): never
    {
        throw ValidationException::withMessages(['file' => trans('human_resources::ai.files.unreadable')]);
    }
}
