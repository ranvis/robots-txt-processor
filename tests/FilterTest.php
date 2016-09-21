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
        $source = "User-agent: a\nDisallow: /path-a\n\nUser-agent: b\nDisallow: /path-b\n\nUser-agent: *\nDisallow: /fallback";
        $filter->setSource($source);
        $record = $filter->getRecord($target);
        $it = $record ? $record->getIterator() : null;
        $line = $it ? $it->current() : null;
        $line = $line ? $record->lineToString($line) : null;
        $this->assertSame($expected, $line);
        if ($it) {
            $it->next();
        }
        $this->assertTrue(!$it || !$it->valid());
    }

    public function getTestSetUserAgentsData()
    {
        return [
            [['a'], 'a', "Disallow: /path-a\x0d\x0a"],
            [['a'], 'b', "Disallow: /fallback\x0d\x0a"],
            [['a'], 'c', "Disallow: /fallback\x0d\x0a"],
            [[['a', 'b']], 'a', "Disallow: /path-a\x0d\x0a"],
            [[['a', 'b']], null, "Disallow: /path-a\x0d\x0a"],
            [[['b', 'a']], 'a', "Disallow: /path-a\x0d\x0a"],
            [[['b', 'a']], null, "Disallow: /path-b\x0d\x0a"],
            [[['a', 'b']], 'b', "Disallow: /path-b\x0d\x0a"],
            [[['a', 'b']], 'c', "Disallow: /fallback\x0d\x0a"],
            [['c'], 'a', "Disallow: /fallback\x0d\x0a"],
            [['c'], 'b', "Disallow: /fallback\x0d\x0a"],
            [['c'], 'c', "Disallow: /fallback\x0d\x0a"],
            [['a', false], 'a', "Disallow: /path-a\x0d\x0a"],
            [['a', false], 'b', null],
            [['a', false], 'c', null],
            [[[]], 'a', "Disallow: /fallback\x0d\x0a"],
            [[false], 'a', "Disallow: /path-a\x0d\x0a"],
        ];
    }

    public function testSetSource()
    {
        $filter = new Filter();
        $filter->setSource('');
        $this->assertNull($filter->getRecord('a'));
        $this->assertNull($filter->getRecord('*'));
        $filter->setSource("User-Agent: Aa\nDisallow: /");
        $this->assertNotNull($filter->getRecord('aA'));
    }

    /**
     * @dataProvider getTestGetFilteredSourceData
     */
    public function testGetFilteredSource($ua, $expected)
    {
        $filter = new Filter();
        $source = "User-agent: a\nDisallow:/path-a\n\n";
        $source .= "User-agent: b\nDisallow:/path-b\n\n";
        $source .= "User-agent: *\nDisallow:/fallback\n\n";
        $source .= "key:non-group\n\n";
        $filter->setSource($source);
        $this->assertSame(str_replace("\n", "\x0d\x0a", $expected), $filter->getFilteredSource($ua));
    }

    public function getTestGetFilteredSourceData()
    {
        return [
            ['A', "User-agent: *\nDisallow: /path-a\n\nKey: non-group\n"],
            ['B', "User-agent: *\nDisallow: /path-b\n\nKey: non-group\n"],
            ['C', "User-agent: *\nDisallow: /fallback\n\nKey: non-group\n"],
        ];
    }

    public function testGetRecord()
    {
        // TODO:
    }

    /**
     * @dataProvider getTestGetNonGroupRecordData
     */
    public function testGetNonGroupRecord($source, $expected)
    {
        $filter = new Filter();
        $filter->setSource($source);
        $this->assertSame(str_replace("\n", "\x0d\x0a", $expected), (string)$filter->getNonGroupRecord());
    }

    public function getTestGetNonGroupRecordData()
    {
        return [
            [
                "Sitemap:non-group\n\nUser-agent: a\ndata-a\n\nUser-agent: b\ndata-b\n\nUser-agent: *\nfallback",
                "Sitemap: non-group\n"
            ], [
                "User-agent: a\ndata-a\n\nUser-agent: b\ndata-b\n\nUser-agent: *\nfallback\n\nSitemap:non-group",
                "Sitemap: non-group\n"
            ], [
                "Sitemap:non-group-1\n\nUser-agent: a\ndata-a\n\nUser-agent: b\ndata-b\n\nUser-agent: *\nfallback\n\nSitemap:non-group-2",
                "Sitemap: non-group-1\nSitemap: non-group-2\n"
            ],
        ];
    }

    public function testOptionMaxRecords()
    {
        $source = "User-agent: a\nDisallow: /path-a\n\nUser-agent: b\nUser-agent: b2\nDisallow: /path-b\n\nUser-agent: c\nDisallow: /path-c";
        $filter = new Filter(['maxRecords' => 1000]);
        $filter->setSource($source);
        $this->assertSame("Disallow: /path-c\x0d\x0a", (string)$filter->getRecord('c'));
        $filter = new Filter(['maxRecords' => 3]);
        $filter->setSource($source);
        $this->assertSame("Disallow: /path-c\x0d\x0a", (string)$filter->getRecord('c'));
        $filter = new Filter(['maxRecords' => 2]);
        $filter->setSource($source);
        $this->assertSame('', (string)$filter->getRecord('c'));
        $this->assertSame("Disallow: /path-b\x0d\x0a", (string)$filter->getRecord('b'));
        $this->assertSame("Disallow: /path-b\x0d\x0a", (string)$filter->getRecord('b2'));
        $filter = new Filter(['maxRecords' => 0]);
        $this->assertNull($filter->getRecord('a'));
        $this->assertNull($filter->getNonGroupRecord());
    }

    public function testGetValue()
    {
        $filter = new Filter();
        $source = "User-agent: *\nDisallow: /\nCrawl-delay: 30\nCrawl-delay: 60\nCrawl-delay: 90";
        $filter->setSource($source);
        $this->assertSame('30', $filter->getValue('Crawl-delay'));
        $this->assertSame('30', $filter->getValue('CRAWL-DELAY'));
    }

    public function testGetNonGroupValue()
    {
        $filter = new Filter();
        $source = "User-agent: *\nDisallow: /\nSitemap: foo\n\nSitemap: bar\nSitemap: baz";
        $filter->setSource($source);
        $this->assertSame('foo', $filter->getNonGroupValue('sitemap'));
    }

    public function testGetNonGroupIterator()
    {
        $filter = new Filter();
        $source = "User-agent: *\nDisallow: /\nSitemap: foo\n\nSitemap: bar\nSitemap: baz";
        $filter->setSource($source);
        $this->assertSame(['foo', 'bar', 'baz'], iterator_to_array($filter->getNonGroupIterator('sitemap')));
        $source = "Sitemap: foo\n\nUser-agent: *\nDisallow: /\n\nSitemap: bar\nSitemap: baz\n\nSitemap: qux";
        $filter->setSource($source);
        $this->assertSame(['foo', 'bar', 'baz', 'qux'], iterator_to_array($filter->getNonGroupIterator('sitemap')));
    }
}
