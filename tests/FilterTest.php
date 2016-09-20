<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

use Ranvis\RobotsTxt\Filter;

class FilterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestSetUserAgentsData
     */
    public function testSetUserAgents($filterArgs, $target, $expected)
    {
        $filter = new Filter();
        $filter->setUserAgents(...$filterArgs);
        $filter->addRecord(['userAgents' => ['a'], 'rules' => 'data-a']);
        $filter->addRecord(['userAgents' => ['b'], 'rules' => 'data-b']);
        $filter->addRecord(['userAgents' => ['*'], 'rules' => 'fallback']);
        $this->assertSame($expected, $filter->getRules($target));
    }

    public function getTestSetUserAgentsData()
    {
        return [
            [['a'], 'a', 'data-a'],
            [['a'], 'b', 'fallback'],
            [['a'], 'c', 'fallback'],
            [[['a', 'b']], 'a', 'data-a'],
            [[['a', 'b']], null, 'data-a'],
            [[['b', 'a']], 'a', 'data-a'],
            [[['b', 'a']], null, 'data-b'],
            [[['a', 'b']], 'b', 'data-b'],
            [[['a', 'b']], 'c', 'fallback'],
            [['c'], 'a', 'fallback'],
            [['c'], 'b', 'fallback'],
            [['c'], 'c', 'fallback'],
            [['a', false], 'a', 'data-a'],
            [['a', false], 'b', null],
            [['a', false], 'c', null],
            [[[]], 'a', 'fallback'],
            [[false], 'a', 'data-a'],
        ];
    }

    public function testSetSource()
    {
        $filter = new Filter();
        $filter->setSource('');
        $this->assertNull($filter->getRawRules('a'));
        $this->assertNull($filter->getRawRules('*'));
        $filter->setSource('User-Agent: Aa');
        $this->assertNotNull($filter->getRawRules('aA'));
    }

    /**
     * @dataProvider getTestGetFilteredSourceData
     */
    public function testGetFilteredSource($ua, $data)
    {
        $filter = new Filter();
        $filter->addRecord(['userAgents' => ['a'], 'rules' => 'key:value-a']);
        $filter->addRecord(['userAgents' => ['b'], 'rules' => 'key:value-b']);
        $filter->addRecord(['userAgents' => ['*'], 'rules' => 'key:fallback']);
        $filter->addRecord(['nonGroup' => true, 'rules' => 'key:non-group']);
        $this->assertSame($data, $filter->getFilteredSource($ua));
    }

    public function getTestGetFilteredSourceData()
    {
        return [
            ['A', "User-agent: *\nkey: value-a\n\nkey:non-group"],
            ['B', "User-agent: *\nkey: value-b\n\nkey:non-group"],
            ['C', "User-agent: *\nkey: fallback\n\nkey:non-group"],
        ];
    }

    public function testGetRules()
    {
        // TODO:
    }

    public function testGetRawRules()
    {
        $filter = new Filter();
        $filter->addRecord(['userAgents' => ['a'], 'rules' => 'data-a']);
        $filter->addRecord(['userAgents' => ['b'], 'rules' => 'data-b']);
        $filter->addRecord(['userAgents' => ['*'], 'rules' => 'fallback']);
        $filter->addRecord(['nonGroup' => true, 'rules' => 'non-group']);
        $this->assertSame('data-a', $filter->getRawRules('a'));
        $this->assertSame('data-b', $filter->getRawRules('b'));
        $this->assertSame(null, $filter->getRawRules('c'));
        $this->assertSame('fallback', $filter->getRawRules('*'));
    }

    /**
     * @dataProvider getTestGetNonGroupRulesData
     */
    public function testGetNonGroupRules($source, $expected)
    {
        $filter = new Filter();
        $filter->setSource($source);
        $this->assertSame($expected, $filter->getNonGroupRules());
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
        $filter = new Filter(['maxRecords' => 1000]);
        $filter->setSource($source);
        $this->assertSame('data-c', $filter->getRawRules('c'));
        $filter = new Filter(['maxRecords' => 3]);
        $filter->setSource($source);
        $this->assertSame('data-c', $filter->getRawRules('c'));
        $filter = new Filter(['maxRecords' => 2]);
        $filter->setSource($source);
        $this->assertNull($filter->getRawRules('c'));
        $this->assertSame('data-b', $filter->getRawRules('b'));
        $this->assertSame('data-b', $filter->getRawRules('b2'));
        $filter = new Filter(['maxRecords' => 0]);
        $this->assertNull($filter->getRawRules('a'));
        $this->assertNull($filter->getNonGroupRules());
    }
}
