<?php

declare(strict_types=1);

namespace Pdf\Import;

use Pdf\Exception\PdfException;

/**
 * Reads a PDF file's object graph.
 *
 * Supports classic `xref` tables and cross-reference streams (PDF 1.5+),
 * object streams, `/Prev` chains and hybrid-reference files. Encrypted files
 * are rejected. Aimed at well-formed output from known tools.
 */
final class PdfReader
{
    /** @var array<int, array{compressed: bool, offset?: int, objStm?: int, index?: int}> */
    private array $xref = [];

    private ?PdfDictionary $trailer = null;

    /** @var array<int, mixed> */
    private array $cache = [];

    /** @var array<int, array{first: int, offsets: array<int, int>, data: string}> */
    private array $objectStreams = [];

    public function __construct(private readonly string $data)
    {
        $this->parseXrefChain();
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new PdfException('PDF file not found: ' . $path);
        }

        return new self((string) file_get_contents($path));
    }

    public function trailer(): PdfDictionary
    {
        return $this->trailer ?? throw new PdfException('PDF has no trailer.');
    }

    public function resolve(mixed $value): mixed
    {
        return $value instanceof PdfReference ? $this->object($value->number) : $value;
    }

    public function object(int $number): mixed
    {
        if (array_key_exists($number, $this->cache)) {
            return $this->cache[$number];
        }

        $entry = $this->xref[$number] ?? null;
        if ($entry === null) {
            return $this->cache[$number] = null;
        }

        if ($entry['compressed']) {
            $stream = $this->objectStream($entry['objStm']);
            $offset = $stream['offsets'][$number] ?? null;
            if ($offset === null) {
                return $this->cache[$number] = null;
            }
            $parser = new PdfParser($stream['data'], $stream['first'] + $offset);

            return $this->cache[$number] = $parser->parseValue();
        }

        $parser = new PdfParser($this->data, $entry['offset']);

        return $this->cache[$number] = $parser->parseIndirectObject($this->referenceResolver());
    }

    private function referenceResolver(): \Closure
    {
        return fn (PdfReference $ref): mixed => $this->object($ref->number);
    }

    // --- xref ---

    private function parseXrefChain(): void
    {
        $start = strrpos($this->data, 'startxref');
        if ($start === false) {
            throw new PdfException('No startxref in PDF.');
        }
        $parser = new PdfParser($this->data, $start + 9);
        $offset = $parser->parseValue();
        if (!is_int($offset)) {
            throw new PdfException('Invalid startxref offset.');
        }

        /** @var array<int, bool> $seen */
        $seen = [];
        $this->readXrefAt($offset, $seen);

        if ($this->trailer !== null && $this->trailer->has('Encrypt')) {
            throw new PdfException('Encrypted PDFs cannot be imported.');
        }
        if ($this->xref === []) {
            throw new PdfException('PDF cross-reference table is empty or unreadable.');
        }
    }

    /** @param array<int, bool> $seen */
    private function readXrefAt(int $offset, array &$seen): void
    {
        if ($offset < 0 || $offset >= strlen($this->data) || isset($seen[$offset])) {
            return;
        }
        $seen[$offset] = true;

        $parser = new PdfParser($this->data, $offset);
        $parser->skipWhitespace();

        if (substr($this->data, $parser->position(), 4) === 'xref') {
            $this->readClassicXref($parser, $seen);

            return;
        }

        $this->readXrefStream($parser, $seen);
    }

    /** @param array<int, bool> $seen */
    private function readClassicXref(PdfParser $parser, array &$seen): void
    {
        $parser->expectKeyword('xref');

        while (true) {
            $parser->skipWhitespace();
            if (substr($this->data, $parser->position(), 7) === 'trailer') {
                break;
            }
            $first = $parser->parseValue();
            $count = $parser->parseValue();
            if (!is_int($first) || !is_int($count)) {
                throw new PdfException('Malformed classic xref subsection header.');
            }
            $parser->skipWhitespace();
            $pos = $parser->position();
            for ($i = 0; $i < $count; $i++) {
                $line = substr($this->data, $pos, 20);
                $pos += 20;
                $objOffset = (int) substr($line, 0, 10);
                $type = trim(substr($line, 17, 1));
                $number = $first + $i;
                if ($type === 'n' && !isset($this->xref[$number])) {
                    $this->xref[$number] = ['compressed' => false, 'offset' => $objOffset];
                }
            }
            $parser->seek($pos);
        }

        $parser->expectKeyword('trailer');
        $trailer = $parser->parseValue();
        if (!$trailer instanceof PdfDictionary) {
            throw new PdfException('Classic xref trailer is not a dictionary.');
        }
        $this->trailer ??= $trailer;

        $xrefStm = $trailer->get('XRefStm');
        if (is_int($xrefStm)) {
            $this->readXrefAt($xrefStm, $seen);
        }
        $prev = $trailer->get('Prev');
        if (is_int($prev)) {
            $this->readXrefAt($prev, $seen);
        }
    }

    /** @param array<int, bool> $seen */
    private function readXrefStream(PdfParser $parser, array &$seen): void
    {
        $stream = $parser->parseIndirectObject();
        if (!$stream instanceof PdfStream) {
            throw new PdfException('Expected a cross-reference stream.');
        }
        $dict = new PdfDictionary($stream->dict);
        $this->trailer ??= $dict;

        $w = $dict->get('W');
        if (!is_array($w) || count($w) !== 3) {
            throw new PdfException('Cross-reference stream is missing /W.');
        }
        [$w0, $w1, $w2] = array_map('intval', $w);
        $size = is_int($dict->get('Size')) ? $dict->get('Size') : 0;

        $index = $dict->get('Index');
        if (!is_array($index) || $index === []) {
            $index = [0, $size];
        }
        $index = array_map('intval', $index);

        $bytes = $stream->decoded();
        $recordWidth = $w0 + $w1 + $w2;
        $cursor = 0;

        for ($s = 0; $s < count($index); $s += 2) {
            $start = $index[$s];
            $count = $index[$s + 1];
            for ($i = 0; $i < $count; $i++) {
                $record = substr($bytes, $cursor, $recordWidth);
                $cursor += $recordWidth;
                if (strlen($record) < $recordWidth) {
                    break 2;
                }
                $type = $w0 === 0 ? 1 : self::readBigEndian(substr($record, 0, $w0));
                $field2 = self::readBigEndian(substr($record, $w0, $w1));
                $field3 = self::readBigEndian(substr($record, $w0 + $w1, $w2));
                $number = $start + $i;
                if (isset($this->xref[$number])) {
                    continue;
                }
                if ($type === 1) {
                    $this->xref[$number] = ['compressed' => false, 'offset' => $field2];
                } elseif ($type === 2) {
                    $this->xref[$number] = ['compressed' => true, 'objStm' => $field2, 'index' => $field3];
                }
            }
        }

        $prev = $dict->get('Prev');
        if (is_int($prev)) {
            $this->readXrefAt($prev, $seen);
        }
    }

    /** @return array{first: int, offsets: array<int, int>, data: string} */
    private function objectStream(int $number): array
    {
        if (isset($this->objectStreams[$number])) {
            return $this->objectStreams[$number];
        }

        $stream = $this->object($number);
        if (!$stream instanceof PdfStream) {
            throw new PdfException('Object stream ' . $number . ' is not a stream.');
        }
        $n = $stream->dict['N'] ?? null;
        $first = $stream->dict['First'] ?? null;
        if (!is_int($n) || !is_int($first)) {
            throw new PdfException('Object stream ' . $number . ' is missing /N or /First.');
        }

        $data = $stream->decoded();
        $header = new PdfParser($data, 0);
        $offsets = [];
        for ($i = 0; $i < $n; $i++) {
            $objNum = $header->parseValue();
            $objOffset = $header->parseValue();
            if (!is_int($objNum) || !is_int($objOffset)) {
                throw new PdfException('Malformed object stream header.');
            }
            $offsets[$objNum] = $objOffset;
        }

        return $this->objectStreams[$number] = ['first' => $first, 'offsets' => $offsets, 'data' => $data];
    }

    private static function readBigEndian(string $bytes): int
    {
        $value = 0;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }

        return $value;
    }
}
