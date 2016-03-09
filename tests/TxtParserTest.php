<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

use \Ranvis\RobotsTxt\TxtParser;

class TxtParserTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestGetRecordIteratorData
     */
    public function testGetRecordIterator($nl)
    {
        $parser = new TxtParser();
        $source = "line-1\n#comment\nline-2\n\nline-3\n\n\nline-4";
        $expected0 = "line-1\n#comment\nline-2";
        $source = str_replace("\n", $nl, $source);
        $expected0 = str_replace("\n", $nl, $expected0);
        $it = $parser->getRecordIterator($source);
        $this->assertSame([$expected0, "line-3", "line-4"], iterator_to_array($it));
    }

    /**
     * @dataProvider getTestGetRecordIteratorData
     */
    public function testGetRecordIterator2($nl)
    {
        $parser = new TxtParser();
        $source = "\n\n\nline-1\n#comment\nline-2\n\nline-3\n\n\nline-4";
        $expected0 = "line-1\n#comment\nline-2";
        $source = str_replace("\n", $nl, $source);
        $expected0 = str_replace("\n", $nl, $expected0);
        $it = $parser->getRecordIterator($source);
        $this->assertSame([$expected0, "line-3", "line-4"], iterator_to_array($it));
    }

    public function getTestGetRecordIteratorData()
    {
        return [
            ["\x0a"],
            ["\x0d\x0a"],
            ["\x0d"],
        ];
    }

    public function testGetRecordSpec()
    {
        $parser = new TxtParser();
        $record = $parser->getRecordSpec("User-agent: abc\nUser-agent:def\nRecord");
        $this->assertSame(['abc', 'def'], $record['userAgents']);
        $this->assertSame('Record', $record['rules']);
        $record = $parser->getRecordSpec("User-agent: 001\nUser-agent:1\nUser-agent: 00");
        $this->assertSame(['001', '1', '00'], $record['userAgents']);
    }

    public function testGetLineIterator()
    {
        $source = "line-1 # comment\n#comment\n \t # comment\nline-2 \t \nline-3";
        $expectedList = ["line-1", "line-2 \t ", "line-3"];
        $parser = new TxtParser();
        foreach ($parser->getLineIterator($source) as $line) {
            $expected = array_shift($expectedList);
            $this->assertSame($expected, $line);
        }
    }

    public function testGetRuleIterator()
    {
        $source = ["Name: value", "Allow:***path", "Disallow:  /path \t", "Disallow: not-this-path", "No;Colon", "Name \t:\t va***lue\t "];
        $expectedList = [
            ['key' => "Name", 'value' => "value"],
            ['key' => "Allow", 'value' => "*path"],
            ['key' => "Disallow", 'value' => "/path \t"],
            ['key' => "Name", 'value' => "va***lue\t "],
        ];
        $parser = new TxtParser();
        foreach ($parser->getRuleIterator($source) as $rule) {
            $expected = array_shift($expectedList);
            $this->assertSame($expected['key'], $rule['key']);
            $this->assertSame($expected['value'], $rule['value']);
        }
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
    public function testOptionMaxUserAgents($max, $expected)
    {
        $source = "User-agent: a\nUser-agent: b\nUser-agent: c\ndata";
        $parser = new TxtParser(['maxUserAgents' => $max]);
        $spec = $parser->getRecordSpec($source);
        $this->assertSame($expected, $spec['userAgents']);
    }

    public function getTestOptionMaxUserAgentsData()
    {
        return [
            [1000, ['a', 'b', 'c']],
            [3, ['a', 'b', 'c']],
            [2, ['a', 'b']],
            [0, []],
        ];
    }

    /**
     * @dataProvider getTestOptionMaxNameLengthData
     */
    public function testOptionMaxNameLength($max, $userAgents)
    {
        $source = "User-agent: abc\nUser-agent: abcde\nUser-agent: abcd\ndata";
        $parser = new TxtParser(['maxNameLength' => $max]);
        $spec = $parser->getRecordSpec($source);
        $this->assertSame($userAgents, $spec['userAgents']);
    }

    public function getTestOptionMaxNameLengthData()
    {
        return [
            [1000, ['abc', 'abcde', 'abcd']],
            [5, ['abc', 'abcde', 'abcd']],
            [4, ['abc', 'abcd']],
            [3, ['abc']],
            [2, []],
            [0, []],
        ];
    }

    /**
     * @dataProvider getTestOptionMaxLineLengthData
     */
    public function testOptionMaxLineLength($max, $source, $expected)
    {
        $parser = new TxtParser(['maxLineLength' => $max]);
        $actual = implode("\n", iterator_to_array($parser->getLineIterator($source)));
        $this->assertSame($expected, $actual);
    }

    public function getTestOptionMaxLineLengthData()
    {
        return [
            [1000, "123456789012345678\n123456789012345", "123456789012345678\n123456789012345"],
            [18, "123456789012345678\n123456789012345", "123456789012345678\n123456789012345"],
            [17, "123456789012345678\n123456789012345", "123456789012345"],
            [15, "123456789012345678\n123456789012345", "123456789012345"],
            [14, "123456789012345678\n123456789012345", ""],
            [12, "123456789012345678\n123456789012345", ""],
            [0, "123456789012345678\n123456789012345", ""],
        ];
    }

    /**
     * @dataProvider getTestOptionMaxWildcardsData
     */
    public function testOptionMaxWildcards($max, $source, $expected)
    {
        $parser = new TxtParser(['maxWildcards' => $max]);
        $actual = $this->getRuleValues($parser->getRuleIterator([$source]));
        $this->assertSame($expected, $actual);
    }

    public function getTestOptionMaxWildcardsData()
    {
        return [
            [5, 'Name: *****v****a***l**u*e', ['*****v****a***l**u*e']],
            [4, 'Name: *****v****a***l**u*e', ['*****v****a***l**u*e']],
            [1, 'Name: *****v****a***l**u*e', ['*****v****a***l**u*e']],
            [0, 'Name: *****v****a***l**u*e', ['*****v****a***l**u*e']],
            [5, 'Allow: *****v****a***l**u*e', ['*v*a*l*u*e']],
            [4, "Allow: *****v****a***l**u*e", []],
            [4, "Allow: *****v****a***l**ue\nAllow: *****v****a***l**u*e", ['*v*a*l*ue']],
        ];
    }

    /**
     * @dataProvider getTestOptionEscapedWildcardData
     */
    public function testOptionEscapedWildcard($max, $flag, $source, $expected)
    {
        $parser = new TxtParser(['maxWildcards' => $max, 'escapedWildcard' => $flag]);
        $actual = $this->getRuleValues($parser->getRuleIterator([$source]));
        $this->assertSame($expected, $actual);
    }

    public function getTestOptionEscapedWildcardData()
    {
        return [
            [3, true, 'Disallow: */%2a%2A/%2a%2A', ['*/*/*']],
            [3, true, 'Disallow: */%2a%2A*%2a%2A', ['*/*']],
            [2, true, 'Disallow: */%2a%2A/%2a%2A', []],
            [2, true, 'Disallow: */%2a%2A*%2a%2A', ['*/*']],
            [3, false, 'Disallow: */%2a%2A/%2a%2A', ['*/%2a%2A/%2a%2A']],
            [3, false, 'Disallow: */%2a%2A/%2a%2A', ['*/%2a%2A/%2a%2A']],
            [2, false, 'Disallow: */%2a%2A/%2a%2A', ['*/%2a%2A/%2a%2A']],
            [2, false, 'Disallow: */%2a%2A*%2a%2A', ['*/%2a%2A*%2a%2A']],
            [1, false, 'Disallow: */%2a%2A/%2a%2A', ['*/%2a%2A/%2a%2A']],
            [0, false, 'Disallow: */%2a%2A/%2a%2A', []],
        ];
    }

    public function testOptionSupportLws()
    {
        $parser = new TxtParser(['supportLws' => false]);
        $expected = $source = "User-agent\n \t: \t\n \ta\nDisallow \t : /";
        $it = $parser->getLineIterator($source);
        $this->assertSame("User-agent\n: \t\na\nDisallow \t : /", implode("\n", iterator_to_array($it)));
        $parser = new TxtParser(['supportLws' => true]);
        $source = str_replace("\n", "\x0d\x0a", $source);
        $it = $parser->getLineIterator($source);
        $expected = "User-agent : \t a\nDisallow \t : /";
        $this->assertSame($expected, implode("\n", iterator_to_array($it)));
    }
}
