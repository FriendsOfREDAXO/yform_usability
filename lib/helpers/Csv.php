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


class Csv
{
    protected        $stream;
    protected array  $headColumns = [];
    protected array  $rows        = [];
    protected string $separator   = ';';
    protected string $enclosure   = '"';
    protected string $escape      = "\\";
    protected string $lineEnding  = "\n";


    public function __construct()
    {
        $this->stream = fopen('php://temp', 'r+');
    }

    public function setHeadColumns(array $headColumns): void
    {
        $this->headColumns = $headColumns;
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

    public function getIndexByHeadColumnName(string $name)
    {
        return array_search($name, $this->headColumns);
    }

    public function writeFile(string $filePath): void
    {
        $folderPath = dirname($filePath);
        rex_dir::create($folderPath);
        rex_file::put($filePath, $this->getStream());
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

    protected function getStream(): ?string
    {
        // Add BOM to fix UTF-8 in Excel
        fwrite($this->stream, chr(0xEF) . chr(0xBB) . chr(0xBF));
        // set headline
        $this->fPutCsv($this->headColumns);
        foreach ($this->rows as $item) {
            if ($this->enclosure == '"') {
                foreach ($item as &$value) {
                    $value = str_replace('"', '""', $value);
                }
            }
            $this->fPutCsv($item);
        }
        rewind($this->stream);
        $stream = stream_get_contents($this->stream);
        return false === $stream ? null : $stream;
    }

    protected function fPutCsv(array $data): void
    {
        $glue = $this->enclosure . $this->separator . $this->enclosure;
        fwrite($this->stream, $this->enclosure . implode($glue, $data) . $this->enclosure . $this->lineEnding);
    }

}