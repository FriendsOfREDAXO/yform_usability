<?php

/**
 * @author Kreatif GmbH
 * @author a.platter@kreatif.it
 * Date: 22.06.21
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace yform\usability\lib\helpers;


use rex_dir;
use rex_file;
use rex_response;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use yform\usability\Usability;


class Csv
{
    protected array  $headColumns = [];
    protected array  $rows        = [];
    protected string $separator   = ';';
    protected string $enclosure   = '"';
    protected string $escape      = "\\";
    protected string $lineEnding  = "\n";
    protected bool   $useUTF8Bom  = false;

    public function hasRows(): bool
    {
        return count($this->rows) > 0;
    }

    public function setHeadColumns(array $headColumns): void
    {
        $this->headColumns = $headColumns;
    }

    public function getHeadColumns(): array
    {
        return $this->headColumns;
    }

    public function addHeadColumn(string $columnName): void
    {
        $this->headColumns[] = $columnName;
    }

    public function setSeparator(string $separator): void
    {
        $this->separator = $separator;
    }

    public function setEnclosure(string $enclosure): void
    {
        $this->enclosure = $enclosure;
    }

    public function setEscape(string $escape): void
    {
        $this->escape = $escape;
    }

    public function setLineEnding(string $lineEnding): void
    {
        $this->lineEnding = $lineEnding;
    }

    public function addRow(array $rowValues): void
    {
        $this->rows[] = $rowValues;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function setRows(array $rows): void
    {
        $this->rows = $rows;
    }

    public function useUTF8Bom(bool $option): void
    {
        $this->useUTF8Bom = $option;
    }

    public function getIndexByHeadColumnName(string $name)
    {
        return array_search($name, $this->headColumns);
    }

    public function writeFile(string $filePath): bool
    {
        $folderPath = dirname($filePath);
        rex_dir::create($folderPath);
        return rex_file::put($filePath, $this->getStream());
    }

    public function sendFile(string $fileName): void
    {
        rex_response::cleanOutputBuffers();
        //then send the headers to force download the zip file
        header("Content-type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename={$fileName}");
        echo $this->getStream();
        exit;
    }

    public function getStream(): ?string
    {
        Usability::includeAutoload();

        if (empty($this->headColumns)) {
            $_rows = $this->rows;
        } else {
            $_rows = [];
            foreach ($this->rows as $row) {
                $_row = [];
                foreach ($row as $column => $value) {
                    $_row[$this->headColumns[$column]] = $value;
                }
                $_rows[] = $_row;
            }
        }

        $encoder = new CsvEncoder();
        return $encoder->encode(
            $_rows,
            'csv',
            [
                CsvEncoder::DELIMITER_KEY       => $this->separator,
                CsvEncoder::ENCLOSURE_KEY       => $this->enclosure,
                CsvEncoder::END_OF_LINE         => $this->lineEnding,
                CsvEncoder::ESCAPE_CHAR_KEY     => $this->escape,
                CsvEncoder::HEADERS_KEY         => $this->headColumns,
                CsvEncoder::OUTPUT_UTF8_BOM_KEY => $this->useUTF8Bom,
                CsvEncoder::NO_HEADERS_KEY      => empty($this->headColumns),
                CsvEncoder::ESCAPE_FORMULAS_KEY => false,
                CsvEncoder::AS_COLLECTION_KEY   => true,
                CsvEncoder::KEY_SEPARATOR_KEY   => '.',
            ]
        );
    }

    public function sendHtml(): void
    {
        rex_response::cleanOutputBuffers();
        $html = ['<table cellpadding="5" cellspacing="0" style="width:100%;" border="1">'];

        if (count($this->headColumns)) {
            $html[] = '<tr><th>' . implode('</th><th>', $this->headColumns) . '</th></tr>';
        }
        foreach ($this->rows as $row) {
            $html[] = '<tr><td style="white-space:nowrap">' . implode('</td><td style="white-space:nowrap;">', $row) . '</td></tr>';
        }
        $html[] = '</table>';
        echo implode('', $html);
        exit;
    }

}