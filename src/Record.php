<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class Record implements RecordInterface
{
    protected $lines = [];

    public function __construct(array $options = [])
    {
    }

    public function addLine(array $line)
    {
        $this->lines[] = $line;
    }

    public function __toString()
    {
        $result = '';
        foreach ($this as $line) {
            $result .= static::lineToString($line);
        }
        return $result;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->lines);
    }

    public static function lineToString(array $line)
    {
        return $line['field'] . ': ' . $line['value'] . "\x0d\x0a";
    }

    public function getValueIterator(string $directive)
    {
        $directive = ucfirst(strtolower($directive));
        foreach ($this as $line) {
            if ($line['field'] === $directive) {
                yield $line['value'];
            }
        }
    }

}
