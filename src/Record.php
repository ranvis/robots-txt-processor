<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class Record implements \IteratorAggregate
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

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->lines);
    }

    protected static function lineToString(array $line)
    {
        return $line['field'] . ': ' . $line['value'] . "\x0d\x0a";
    }

    /**
     * Get the first value of the directive
     *
     * @param string $directive directive name like Crawl-delay
     * @return ?string value of the directive
     */
    public function getValue(string $directive)
    {
        $it = $this->getValueIterator($directive);
        return $it->valid() ? $it->current() : null;
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
