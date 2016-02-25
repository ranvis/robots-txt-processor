<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

class FilterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestSetUserAgentsData
     */
    public function testSetUserAgents($filterArgs, $target, $expected)
    {
        $source = "User-agent: a\ndata-a\n\nUser-agent: b\ndata-b\n\nUser-agent: *\nfallback";
        $parser = new \Ranvis\RobotsTxt\Filter();
        $parser->setUserAgents(...$filterArgs);
        $parser->parse($source);
        $this->assertSame($expected, $parser->getRules($target));
    }

    public function getTestSetUserAgentsData()
    {
        return [
            [['a'], 'a', 'data-a'],
            [['a'], 'b', 'fallback'],
            [['a'], 'c', 'fallback'],
            [[['a', 'b']], 'a', 'data-a'],
            [[['a', 'b']], 'b', 'data-b'],
            [[['a', 'b']], 'c', 'fallback'],
            [['c'], 'a', 'fallback'],
            [['c'], 'b', 'fallback'],
            [['c'], 'c', 'fallback'],
            [['a', false], 'a', 'data-a'],
            [['a', false], 'b', null],
            [['a', false], 'c', null],
        ];
    }

    public function testParse()
    {
        $parser = new \Ranvis\RobotsTxt\Filter();
        $parser->parse('');
        $this->assertNull($parser->getRawRules('a'));
        $this->assertNull($parser->getRawRules('*'));
    }

    /**
     * @dataProvider getTestGetFilteredSourceData
     */
    public function testGetFilteredSource($ua, $data)
    {
        $source = "User-agent: a\nkey:value-a\n\nUser-agent: b\nkey:value-b\n\nUser-agent: *\nkey:fallback\n\nkey:non-group";
        $parser = new \Ranvis\RobotsTxt\Filter();
        $parser->parse($source);
        $this->assertSame($data, $parser->getFilteredSource($ua));
    }

    public function getTestGetFilteredSourceData()
    {
        return [
            ['a', "User-agent: *\nkey: value-a\n\nkey:non-group"],
            ['b', "User-agent: *\nkey: value-b\n\nkey:non-group"],
            ['c', "User-agent: *\nkey: fallback\n\nkey:non-group"],
        ];
    }

    public function testGetRules()
    {
        // TODO:
    }

    public function testGetRawRules()
    {
        $source = "User-agent: a\ndata-a\n\nUser-agent: b\ndata-b\n\nUser-agent: *\nfallback\n\nnon-group";
        $parser = new \Ranvis\RobotsTxt\Filter();
        $parser->parse($source);
        $this->assertSame('data-a', $parser->getRawRules('a'));
        $this->assertSame('data-b', $parser->getRawRules('b'));
        $this->assertSame(null, $parser->getRawRules('c'));
        $this->assertSame('fallback', $parser->getRawRules('*'));
    }

    /**
     * @dataProvider getTestGetNonGroupRulesData
     */
    public function testGetNonGroupRules($source, $expected)
    {
        $parser = new \Ranvis\RobotsTxt\Filter();
        $parser->parse($source);
        $this->assertSame($expected, $parser->getNonGroupRules());
    }

    public function getTestGetNonGroupRulesData()
    {
        return [
            [
                "non-group\n\nUser-agent: a\ndata-a\n\nUser-agent: b\ndata-b\n\nUser-agent: *\nfallback",
                "non-group"
            ], [
                "User-agent: a\ndata-a\n\nUser-agent: b\ndata-b\n\nUser-agent: *\nfallback\n\nnon-group",
                "non-group"
            ], [
                "non-group-1\n\nUser-agent: a\ndata-a\n\nUser-agent: b\ndata-b\n\nUser-agent: *\nfallback\n\nnon-group-2",
                "non-group-1\nnon-group-2"
            ],
        ];
    }

    public function testOptionMaxRecords()
    {
        $source = "User-agent: a\ndata-a\n\nUser-agent: b\nUser-agent: b2\ndata-b\n\nUser-agent: c\ndata-c";
        $parser = new \Ranvis\RobotsTxt\Filter(['maxRecords' => 1000]);
        $parser->parse($source);
        $this->assertSame('data-c', $parser->getRawRules('c'));
        $parser = new \Ranvis\RobotsTxt\Filter(['maxRecords' => 3]);
        $parser->parse($source);
        $this->assertSame('data-c', $parser->getRawRules('c'));
        $parser = new \Ranvis\RobotsTxt\Filter(['maxRecords' => 2]);
        $parser->parse($source);
        $this->assertNull($parser->getRawRules('c'));
        $this->assertSame('data-b', $parser->getRawRules('b'));
        $this->assertSame('data-b', $parser->getRawRules('b2'));
        $parser = new \Ranvis\RobotsTxt\Filter(['maxRecords' => 0]);
        $this->assertNull($parser->getRawRules('a'));
        $this->assertNull($parser->getNonGroupRules());
    }

    public function testOptionMaxUserAgents()
    {
        $source = "User-agent: a\nUser-agent: b\nUser-agent: c\ndata";
        $parser = new \Ranvis\RobotsTxt\Filter(['maxUserAgents' => 1000]);
        $parser->parse($source);
        $this->assertSame('data', $parser->getRawRules('c'));
        $parser = new \Ranvis\RobotsTxt\Filter(['maxUserAgents' => 3]);
        $parser->parse($source);
        $this->assertSame('data', $parser->getRawRules('c'));
        $parser = new \Ranvis\RobotsTxt\Filter(['maxUserAgents' => 2]);
        $parser->parse($source);
        $this->assertNull($parser->getRawRules('c'));
        $this->assertSame('data', $parser->getRawRules('b'));
        $parser = new \Ranvis\RobotsTxt\Filter(['maxUserAgents' => 0]);
        $this->assertNull($parser->getRawRules('a'));
        $this->assertNull($parser->getNonGroupRules());
    }

    /**
     * @dataProvider getTestOptionMaxNameLengthData
     */
    public function testOptionMaxNameLength($max, $uaRules, $nonGroup)
    {
        $source = "User-agent: 123\nUser-agent: 12345\nUser-agent: 1234\ndata";
        $parser = new \Ranvis\RobotsTxt\Filter(['maxNameLength' => $max]);
        $parser->parse($source);
        $this->assertSame($uaRules[0], $parser->getRawRules('123'));
        $this->assertSame($uaRules[1], $parser->getRawRules('1234'));
        $this->assertSame($uaRules[2], $parser->getRawRules('12345'));
        $this->assertSame($nonGroup, $parser->getNonGroupRules());
    }

    public function getTestOptionMaxNameLengthData()
    {
        return [
            [1000, ['data', 'data', 'data'], null],
            [5, ['data', 'data', 'data'], null],
            [4, ['data', 'data', null], null],
            [3, ['data', null, null], null],
            [2, [null, null, null], null],
            [0, [null, null, null], null],
        ];
    }

    /**
     * @dataProvider getTestOptionMaxLineLengthData
     */
    public function testOptionMaxLineLength($max, $source, $expected)
    {
        $parser = new \Ranvis\RobotsTxt\Filter(['maxLineLength' => $max]);
        $parser->parse($source);
        $this->assertSame($expected, $parser->getRawRules('*'));
    }

    public function getTestOptionMaxLineLengthData()
    {
        return [
            [1000, "User-agent: *\n123456789012345678\n123456789012345", "123456789012345678\n123456789012345"],
            [18, "User-agent: *\n123456789012345678\n123456789012345", "123456789012345678\n123456789012345"],
            [17, "User-agent: *\n123456789012345678\n123456789012345", "123456789012345"],
            [15, "User-agent: *\n123456789012345678\n123456789012345", "123456789012345"],
            [14, "User-agent: *\n123456789012345678\n123456789012345", ""],
            [12, "User-agent: *\n123456789012345678\n123456789012345", null],
            [0, "User-agent: *\n123456789012345678\n123456789012345", null],
        ];
    }

    /**
     * @dataProvider getTestOptionMaxWildcardsData
     */
    public function testOptionMaxWildcards($max, $source, $expected)
    {
        $parser = new \Ranvis\RobotsTxt\Filter(['maxWildcards' => $max]);
        $header = "User-agent: *";
        $parser->parse($header . "\n" . $source);
        $actual = $parser->getFilteredSource('*');
        $this->assertSame($header . (strlen($expected) ? "\n" : '') . $expected, $actual);
    }

    public function getTestOptionMaxWildcardsData()
    {
        return [
            [5, 'Name: *****v****a***l**u*e', 'Name: *****v****a***l**u*e'],
            [4, 'Name: *****v****a***l**u*e', 'Name: *****v****a***l**u*e'],
            [1, 'Name: *****v****a***l**u*e', 'Name: *****v****a***l**u*e'],
            [0, 'Name: *****v****a***l**u*e', 'Name: *****v****a***l**u*e'],
            [5, 'Allow: *****v****a***l**u*e', 'Allow: *v*a*l*u*e'],
            [4, "Allow: *****v****a***l**u*e", ''],
            [4, "Allow: *****v****a***l**ue\nAllow: *****v****a***l**u*e", 'Allow: *v*a*l*ue'],
        ];
    }

    /**
     * @dataProvider getTestOptionEscapedWildcardData
     */
    public function testOptionEscapedWildcard($max, $flag, $source, $expected)
    {
        $parser = new \Ranvis\RobotsTxt\Filter(['maxWildcards' => $max, 'escapedWildcard' => $flag]);
        $header = "User-agent: *";
        $parser->parse($header . "\n" . $source);
        $actual = $parser->getFilteredSource('*');
        $this->assertSame($header . (strlen($expected) ? "\n" : '') . $expected, $actual);
    }

    public function getTestOptionEscapedWildcardData()
    {
        return [
            [3, true, 'Disallow: */%2a%2A/%2a%2A', 'Disallow: */*/*'],
            [3, true, 'Disallow: */%2a%2A*%2a%2A', 'Disallow: */*'],
            [2, true, 'Disallow: */%2a%2A/%2a%2A', ''],
            [2, true, 'Disallow: */%2a%2A*%2a%2A', 'Disallow: */*'],
            [3, false, 'Disallow: */%2a%2A/%2a%2A', 'Disallow: */%2a%2A/%2a%2A'],
            [3, false, 'Disallow: */%2a%2A/%2a%2A', 'Disallow: */%2a%2A/%2a%2A'],
            [2, false, 'Disallow: */%2a%2A/%2a%2A', 'Disallow: */%2a%2A/%2a%2A'],
            [2, false, 'Disallow: */%2a%2A*%2a%2A', 'Disallow: */%2a%2A*%2a%2A'],
            [1, false, 'Disallow: */%2a%2A/%2a%2A', 'Disallow: */%2a%2A/%2a%2A'],
            [0, false, 'Disallow: */%2a%2A/%2a%2A', ''],
        ];
    }

    public function testOptionSupportLws()
    {
        $parser = new \Ranvis\RobotsTxt\Filter(['supportLws' => false]);
        $source = "User-agent\n \t: \t\n \ta";
        $parser->parse($source);
        $this->assertNull($parser->getRawRules('a'));
        $this->assertSame($source, $parser->getNonGroupRules());
        $parser = new \Ranvis\RobotsTxt\Filter(['supportLws' => true]);
        $parser->parse($source);
        $this->assertNull($parser->getRawRules('a'));
        $this->assertSame($source, $parser->getNonGroupRules());
        $source = str_replace("\n", "\x0d\x0a", $source);
        $parser = new \Ranvis\RobotsTxt\Filter(['supportLws' => true]);
        $parser->parse($source);
        $this->assertSame('', $parser->getRawRules('a'));
        $this->assertNull($parser->getNonGroupRules());
    }
}
