<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class FilterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider getTestSetUserAgentsData
     */
    public function testSetUserAgents($filterArgs, $target, $expected): void
    {
        $filter = new Filter();
        $filter->setUserAgents(...$filterArgs);
        $source = "User-agent: a\nDisallow: /path-a\n\nUser-agent: b\nDisallow: /path-b\n\nUser-agent: *\nDisallow: /fallback";
        $recordSet = $filter->getRecordSet($source);
        $record = $recordSet->getRecord($target, false);
        $this->assertSame($expected, $record ? (string)$record : null);
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

    public function testGetRecordSet(): void
    {
        $filter = new Filter();
        $recordSet = $filter->getRecordSet('');
        $this->assertNull($recordSet->getRecord('a', false));
        $this->assertNull($recordSet->getRecord('*', false));
        $this->assertNotNull($recordSet->getRecord('*', true));
        $recordSet = $filter->getRecordSet("User-Agent: Aa\nDisallow: /");
        $this->assertNotNull($recordSet->getRecord('aA', false));
    }

    /**
     * @dataProvider getTestGetNonGroupRecordData
     */
    public function testGetNonGroupRecord($source, $expected): void
    {
        $filter = new Filter();
        $recordSet = $filter->getRecordSet($source);
        $this->assertSame(nlToCrlf($expected), (string)$recordSet->getNonGroupRecord());
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

    public function testOptionMaxRecords(): void
    {
        $source = "User-agent: a\nDisallow: /path-a\n\nUser-agent: b\nUser-agent: b2\nDisallow: /path-b\n\nUser-agent: c\nDisallow: /path-c";
        $filter = new Filter(['maxRecords' => 1000]);
        $recordSet = $filter->getRecordSet($source);
        $this->assertSame("Disallow: /path-c\x0d\x0a", (string)$recordSet->getRecord('c'));
        $filter = new Filter(['maxRecords' => 3]);
        $recordSet = $filter->getRecordSet($source);
        $this->assertSame("Disallow: /path-c\x0d\x0a", (string)$recordSet->getRecord('c'));
        $filter = new Filter(['maxRecords' => 2]);
        $recordSet = $filter->getRecordSet($source);
        $this->assertSame('', (string)$recordSet->getRecord('c'));
        $this->assertSame("Disallow: /path-b\x0d\x0a", (string)$recordSet->getRecord('b'));
        $this->assertSame("Disallow: /path-b\x0d\x0a", (string)$recordSet->getRecord('b2'));
        $filter = new Filter(['maxRecords' => 0]);
        $recordSet = $filter->getRecordSet($source);
        $this->assertNotNull($recordSet);
    }

    public function testGetValue(): void
    {
        $filter = new Filter();
        $source = "User-agent: *\nDisallow: /\nCrawl-delay: 30\nCrawl-delay: 60\nCrawl-delay: 90";
        $record = $filter->getRecordSet($source)->getRecord();
        $this->assertSame('30', $record->getValue('Crawl-delay'));
        $this->assertSame('30', $record->getValue('CRAWL-DELAY'));
    }

    public function testWithNonGroupValue(): void
    {
        $filter = new Filter();
        $source = "User-agent: *\nDisallow: /\nSitemap: foo\n\nSitemap: bar\nSitemap: baz";
        $record = $filter->getRecordSet($source)->getNonGroupRecord();
        $this->assertSame('foo', $record->getValue('sitemap'));
    }

    public function testWithNonGroupValueIterator(): void
    {
        $filter = new Filter();
        $source = "User-agent: *\nDisallow: /\nSitemap: foo\n\nSitemap: bar\nSitemap: baz";
        $record = $filter->getRecordSet($source)->getNonGroupRecord();
        $this->assertSame(['foo', 'bar', 'baz'], iterator_to_array($record->getValueIterator('sitemap'), false));
        $source = "Sitemap: foo\n\nUser-agent: *\nDisallow: /\n\nSitemap: bar\nSitemap: baz\n\nSitemap: qux";
        $record = $filter->getRecordSet($source)->getNonGroupRecord();
        $this->assertSame(['foo', 'bar', 'baz', 'qux'], iterator_to_array($record->getValueIterator('sitemap'), false));
    }
}
