<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class StringRecord extends Record
{
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function __toString()
    {
        return (string)$this->options['string'];
    }

    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        throw new \LogicException('unimplemented');
    }

    public function getValueIterator(string $directive)
    {
        throw new \LogicException('unimplemented');
    }
}
