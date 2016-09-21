<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

interface RecordInterface extends \IteratorAggregate
{
    public function addLine(array $line);
    public function __toString();
}
