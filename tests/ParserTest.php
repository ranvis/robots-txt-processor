<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class ParserTest extends \PHPUnit\Framework\TestCase
{
    public function testGetRecordIterator(): void
    {
        $parser = new Parser();
        $it = $parser->getRecordIterator("User-agent: abc\nUser-agent:def\nDisallow: path");
        $this->assertSame(['abc', 'def'], $it->current()['userAgents']);
        $this->assertSame("Disallow: path\x0d\x0a", (string)$it->current()['record']);
        $it->next();
        $this->assertNull($it->current());
        $it = $parser->getRecordIterator("User-agent: 001\nUser-agent:1\nUser-agent: 00\nDisallow: path");
        $this->assertSame(['001', '1', '00'], $it->current()['userAgents'], "Numeric user-agent strings");
    }

    private function getRuleValues($rules)
    {
        $result = [];
        foreach ($rules as $rule) {
            $result[] = $rule['value'];
        }
        return $result;
    }

    /**
     * @dataProvider getTestOptionMaxUserAgentsData
     */
    public function testOptionMaxUserAgents($max, $expected): void
    {
        $source = "User-agent: a\nUser-agent: b\nUser-agent: c\nallow:data";
        $parser = new Parser(['maxUserAgents' => $max]);
        $it = $parser->getRecordIterator($source);
        $userAgents = $it->current()['userAgents'] ?? null;
        $this->assertSame($expected, $userAgents);
    }

    public function getTestOptionMaxUserAgentsData()
    {
        return [
            [1000, ['a', 'b', 'c']],
            [3, ['a', 'b', 'c']],
            [2, ['a', 'b']],
            [0, null],
        ];
    }

    /**
     * @dataProvider getTestOptionMaxNameLengthData
     */
    public function testOptionMaxNameLength($max, $expected): void
    {
        $source = "User-agent: abc\nUser-agent: abcde\nUser-agent: abcd\nallow:data";
        $parser = new Parser(['maxNameLength' => $max]);
        $it = $parser->getRecordIterator($source);
        $userAgents = $it->current()['userAgents'];
        $this->assertSame($expected, $userAgents);
    }

    public function getTestOptionMaxNameLengthData()
    {
        return [
            [1000, ['abc', 'abcde', 'abcd']],
            [5, ['abc', 'abcde', 'abcd']],
            [4, ['abc', 'abcd', 'abcd']],
            [3, ['abc', 'abc', 'abc']],
            [2, ['ab', 'ab', 'ab']],
        ];
    }

    /**
     * @dataProvider getTestOptionMaxValueLengthData
     */
    public function testOptionMaxValueLength($max, $source, $expected): void
    {
        $parser = new Parser(['maxValueLength' => $max]);
        $this->assertSame($expected, $this->lineIteratorToString($parser->getLineIterator($source)));
    }

    public function getTestOptionMaxValueLengthData()
    {
        return [
            [1000, "Disallow: 123456789012345678\nDisallow: 123456789012345", "Disallow: 123456789012345678\nDisallow: 123456789012345"],
            [18, "Disallow: 123456789012345678\nDisallow: 123456789012345", "Disallow: 123456789012345678\nDisallow: 123456789012345"],
            [17, "Disallow: 123456789012345678\nDisallow: 123456789012345", "-ignored-: 18\nDisallow: 123456789012345"],
            [15, "Disallow: 123456789012345678\nDisallow: 123456789012345", "-ignored-: 18\nDisallow: 123456789012345"],
            [14, "Disallow: 123456789012345678\nDisallow: 123456789012345", "-ignored-: 18\n-ignored-: 15"],
            [12, "Disallow: 123456789012345678\nDisallow: 123456789012345", "-ignored-: 18\n-ignored-: 15"],
            [0, "Disallow: 123456789012345678\nDisallow: 123456789012345", "-ignored-: 18\n-ignored-: 15"],
        ];
    }

    /**
     * @dataProvider getEolData
     */
    public function testEolAndLws($nl): void
    {
        $parser = new Parser();
        $source = "User-agent\n \t:\n \ta\nDisallow\n \t :\n /";
        $source = str_replace("\n", $nl, $source);
        $expected = "User-agent: a\nDisallow: /";
        $this->assertSame($expected, $this->lineIteratorToString($parser->getLineIterator($source)));
        $source = "User-agent \n \t: \n \ta\nDisallow \n\t :\n\n /";
        $source = str_replace("\n", $nl, $source);
        $expected = "";
        $it = $parser->getLineIterator($source);
        $this->assertSame($expected, $this->lineIteratorToString($parser->getLineIterator($source)));
    }

    public function getEolData()
    {
        return [
            ["\x0a"],
            ["\x0d\x0a"],
            ["\x0d"],
        ];
    }

    public function testRegisterGroupDirective(): void
    {
        $source = "User-agent: crawler\nCrawl-delay: 60\nmy-custom-flag: yes\nmy-custom-value: 30";
        $parser = new FilterParser();
        $parser->registerGroupDirective('My-custom-flag');
        $filter = new Filter();
        $recordSet = $filter->getRecordSet($parser->getRecordIterator($source));
        $record = $recordSet->getRecord('crawler');
        $this->assertSame('yes', $record->getValue('My-custom-flag'));
        $record = $recordSet->getRecord('robot');
        $this->assertSame(null, $record->getValue('My-custom-flag'));
        $record = $recordSet->getRecord('crawler');
        $this->assertSame(null, $record->getValue('my-custom-value'));
        $record = $recordSet->getNonGroupRecord();
        $this->assertSame('30', $record->getValue('my-custom-value'));
    }

    private function lineIteratorToString($it)
    {
        $result = [];
        foreach ($it as $line) {
            $result[] = $line['field'] . ': ' . $line['value'];
        }
        return implode("\n", $result);
    }
}
