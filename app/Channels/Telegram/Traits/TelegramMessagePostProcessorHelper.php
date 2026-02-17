<?php

namespace App\Channels\Telegram\Traits;

trait TelegramMessagePostProcessorHelper
{
    protected function postProcessMessage(string $text): string
    {
        // add more post-processing steps here if needed
        return $this->convertMarkdownTables($text);
    }

    private function convertMarkdownTables(string $text): string
    {
        $lines = explode("\n", $text);
        $result = [];
        $inTable = false;
        $headers = [];
        $rows = [];

        foreach ($lines as $line) {
            $isTableLine = str_starts_with(trim($line), '|');
            $isSeparator = preg_match('/^\|[\s\-\|]+\|$/', trim($line));

            if ($isTableLine && !$isSeparator) {
                $inTable = true;
                $cells = array_map('trim', explode('|', trim($line, '|')));

                if (empty($headers)) {
                    $headers = $cells;
                } else {
                    $rows[] = $cells;
                }
            } elseif (!$isTableLine && $inTable) {
                // Kraj tabele - konvertuj
                $result[] = $this->formatTable($headers, $rows);
                $headers = [];
                $rows = [];
                $inTable = false;
                $result[] = $line;
            } elseif (!$isSeparator) {
                $result[] = $line;
            }
        }

        if ($inTable && !empty($headers)) {
            $result[] = $this->formatTable($headers, $rows);
        }

        return implode("\n", $result);
    }

    private function formatTable(array $headers, array $rows): string
    {
        $output = [];

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $i => $header) {
                $value = $row[$i] ?? '-';
                $line[] = "*{$header}*: {$value}";
            }
            $output[] = implode(', ', $line);
        }

        return implode("\n", $output);
    }
}
