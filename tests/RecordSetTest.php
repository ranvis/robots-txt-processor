<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

use \Ranvis\RobotsTxt\Record;
use \Ranvis\RobotsTxt\RecordSet;

class RecordSetTest extends PHPUnit_Framework_TestCase
{
    public function testNonGroup()
    {
        $recordSet = new RecordSet();
        $this->assertNull($recordSet->getNonGroupRecord());
        $this->assertFalse($recordSet->getNonGroupValueIterator('Test')->valid());
        $record = new Record();
        $recordSet->setNonGroup($record);
        $this->assertSame($record, $recordSet->getNonGroupRecord());
        $this->assertFalse($recordSet->getNonGroupValueIterator('Test')->valid());
        $record->addLine(['field' => 'Test', 'value' => 'ok']);
        $this->assertSame('ok', $recordSet->getNonGroupValueIterator('Test')->current());
    }
}
