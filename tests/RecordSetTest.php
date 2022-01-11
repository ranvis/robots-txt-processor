<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class RecordSetTest extends \PHPUnit\Framework\TestCase
{
    public function testNonGroupRecord(): void
    {
        $recordSet = new RecordSet();
        $this->assertNull($recordSet->getNonGroupRecord(false));
        $record = $recordSet->getNonGroupRecord();
        $this->assertNotNull($record);
        $this->assertFalse($record->getValueIterator('Test')->valid());
        $record = new Record();
        $recordSet->setNonGroupRecord($record);
        $this->assertSame($record, $recordSet->getNonGroupRecord());
        $this->assertFalse($record->getValueIterator('Test')->valid());
        $record->addLine(toLine('Test', 'ok'));
        $this->assertSame('ok', $record->getValueIterator('Test')->current());
    }

    public function testExtract(): void
    {
        $record = new StringRecord(['string' => '<1>']);
        $record2 = new StringRecord(['string' => '<2>']);
        $ngRecord = new StringRecord(['string' => '<N>']);
        $recordSet = new RecordSet;
        $this->assertSame('', (string)$recordSet->extract());
        $recordSet->setNonGroupRecord($ngRecord);
        $this->assertSame(nlToCrlf("<N>"), (string)$recordSet->extract());
        $recordSet->add('Rec', $record);
        $this->assertSame(nlToCrlf("User-agent: *\n<1>\n<N>"), (string)$recordSet->extract());
        $recordSet->add('Rec2', $record2);
        $this->assertSame(nlToCrlf("User-agent: *\n<1>\n<N>"), (string)$recordSet->extract());
        $this->assertSame(nlToCrlf("User-agent: *\n<1>\n<N>"), (string)$recordSet->extract('Rec'));
        $this->assertSame(nlToCrlf("User-agent: *\n<2>\n<N>"), (string)$recordSet->extract('Rec2'));
    }
}
