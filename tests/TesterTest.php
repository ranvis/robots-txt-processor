<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class TesterTest extends \PHPUnit_Framework_TestCase
{
    public function testIsResponseCodeAllowed()
    {
        $instance = new Tester(['ignoreForbidden' => false]);
        $this->assertTrue($instance->isResponseCodeAllowed(200)); // OK
        $this->assertTrue($instance->isResponseCodeAllowed(204)); // No Content
        $this->assertFalse($instance->isResponseCodeAllowed(303)); // See Other
        $this->assertFalse($instance->isResponseCodeAllowed(401)); // Unauthorized
        $this->assertFalse($instance->isResponseCodeAllowed(403)); // Forbidden
        $this->assertTrue($instance->isResponseCodeAllowed(410)); // Gone
        $this->assertFalse($instance->isResponseCodeAllowed(500)); // Internal Server Error
        $this->assertFalse($instance->isResponseCodeAllowed(503)); // Service Unavailable
        $instance = new Tester(['ignoreForbidden' => true]);
        $this->assertTrue($instance->isResponseCodeAllowed(200)); // OK
        $this->assertTrue($instance->isResponseCodeAllowed(204)); // No Content
        $this->assertFalse($instance->isResponseCodeAllowed(303)); // See Other
        $this->assertTrue($instance->isResponseCodeAllowed(401)); // Unauthorized
        $this->assertTrue($instance->isResponseCodeAllowed(403)); // Forbidden
        $this->assertTrue($instance->isResponseCodeAllowed(410)); // Gone
        $this->assertFalse($instance->isResponseCodeAllowed(500)); // Internal Server Error
        $this->assertFalse($instance->isResponseCodeAllowed(503)); // Service Unavailable
    }

    public function testSetResponseCode()
    {
        $tester = new Tester();
        $allowAll = "User-agent: *\nDisallow: ";
        $tester->setResponseCode(503);
        $this->assertFalse($tester->isAllowed('/'));
        $tester->setSource($allowAll); // overwrite
        $this->assertTrue($tester->isAllowed('/'));
        $tester->setResponseCode(503); // overwrite
        $this->assertFalse($tester->isAllowed('/'));
    }

    public function testRules()
    {
        $tester = new Tester();
        $source = "User-agent: *\nDisallow: /foo\nDisallow: /foo/bar/baz";
        $tester->setSource($source);
        $this->assertFalse($tester->isAllowed('/foo/bar/'));
        $this->assertFalse($tester->isAllowed('/foo/bar/bazqux'));
        $this->assertTrue($tester->isAllowed('/qux/foo/bar/baz'));
        $source = "User-agent: *\nDisallow: /foo\nDisallow: /foo/bar/baz\nAllow: /foo/bar/";
        $tester->setSource($source);
        $this->assertTrue($tester->isAllowed('/foo/bar/'));
        $this->assertFalse($tester->isAllowed('/foo/bar/bazqux'));
        $this->assertFalse($tester->isAllowed('/foo/b'));
        $this->assertTrue($tester->isAllowed('/qux/foo/bar/baz'));
        $source = "User-agent: *\nDisallow: /*.php$";
        $tester->setSource($source);
        $this->assertFalse($tester->isAllowed('/foo.php'));
        $this->assertFalse($tester->isAllowed('/foo/bar.php'));
        $this->assertTrue($tester->isAllowed('/foo.html'));
        $this->assertTrue($tester->isAllowed('/foo.php/bar'));
        $this->assertTrue($tester->isAllowed('/foo.php?bar'));
        // ranvis/robots-txt-processor-test will be used for more tests
    }

    public function testThatSetSourceFiltersRecords()
    {
        $tester = new Tester();
        $source = "User-agent: *\nDisallow: /\nUser-agent: Permitted\nDisallow:\nUser-agent: Forbidden\nDisallow: /path";
        $filter = new Filter();
        $filter->setUserAgents('Unknown');
        $tester->setSource($filter->getRecordSet($source));
        $this->assertFalse($tester->isAllowed('/path'));
        $this->assertFalse($tester->isAllowed('/', 'Permitted', "should not read on setSource"));
        $filter->setUserAgents('Permitted');
        $tester->setSource($filter->getRecordSet($source));
        $this->assertTrue($tester->isAllowed('/path'));
        $this->assertFalse($tester->isAllowed('/path', 'Forbidden'));
        $this->assertFalse($tester->isAllowed('/', 'Forbidden', "should not read on setSource"));
        $filter->setUserAgents(false);
        $tester->setSource($filter->getRecordSet($source));
        $this->assertFalse($tester->isAllowed('/path'));
        $filter->setUserAgents('Forbidden');
        $tester->setSource($filter->getRecordSet($source));
        $this->assertFalse($tester->isAllowed('/path'));
        $this->assertFalse($tester->isAllowed('/path', 'Permitted', "should not read on setSource"));
        $this->assertFalse($tester->isAllowed('/', 'Permitted', "should not read on setSource"));
        $filter->setUserAgents(['Permitted', 'Forbidden']);
        $tester->setSource($filter->getRecordSet($source));
        $this->assertTrue($tester->isAllowed('/path'));
        $this->assertTrue($tester->isAllowed('/path', 'Permitted'));
        $this->assertFalse($tester->isAllowed('/path', 'Forbidden'));
        $this->assertFalse($tester->isAllowed('/path', 'Unknown'));
        $this->assertTrue($tester->isAllowed('/'));
        $this->assertTrue($tester->isAllowed('/', 'Permitted'));
        $this->assertTrue($tester->isAllowed('/', 'Forbidden'));
        $this->assertFalse($tester->isAllowed('/', 'Unknown'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testThatSetSourceWithRecordSetAndUaThrows()
    {
        try {
            $tester = new Tester();
            $source = "User-agent: *\nDisallow: /";
            $tester->setSource($source, 'Foo');
            $this->assertFalse($tester->isAllowed('/foo'));
        } catch (\InvalidArgumentException $e) {
            throw new \UnexpectedValueException("Unexpected throw", 0, $e);
        }
        $tester->setSource(new RecordSet(), 'Foo'); // throws
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testThatIsAllowedNeedsValidPath()
    {
        $tester = new Tester();
        $tester->setSource("User-agent: *\nDisallow:");
        $tester->isAllowed('foo'); // throws
    }

    /**
     * @expectedException \LogicException
     */
    public function testThatIsAllowedNeedsSource()
    {
        $tester = new Tester();
        $tester->isAllowed('/foo'); // throws
    }

    public function testWithUnknownDirective()
    {
        $record = new Record();
        $record->addLine(toLine('Foo', '/'));
        $recordSet = new RecordSet();
        $recordSet->add('*', $record);
        $tester = new Tester();
        $tester->setSource($recordSet);
        $this->assertTrue($tester->isAllowed('/'));
    }

    public function testNormalizePath()
    {
        $instance = new Tester();
        $method = getInstanceMethod($instance, 'normalizePath');
        $this->assertSame('///%2F%2F', $method('///%2f%2F', false));
        $this->assertSame('/.*?%2A$', $method('/***%2A*$$$$', true));
        $this->assertSame('/%00%01%02%03', $method("/\x00\x01\x02\x03", false));
    }

    public function testCompactRules()
    {
        $instance = new Tester();
        $method = getInstanceMethod($instance, 'compactRules');
        $this->assertSame([], $method([]));
        $this->assertSame([toLine('allow', 'abc')], array_values($method([toLine('allow', 'abc')])));
        $this->assertSame([toLine('allow', 'abc|def')], array_values($method([toLine('allow', 'abc'), toLine('allow', 'def')])));
        $this->assertSame([toLine('allow', 'abc'), toLine('disallow', 'def'), toLine('allow', 'ghi')], array_values($method([toLine('allow', 'abc'), toLine('disallow', 'def'), toLine('allow', 'ghi')])));
        $this->assertSame([toLine('disallow', 'abc|def'), toLine('allow', 'ghi')], array_values($method([toLine('disallow', 'abc'), toLine('disallow', 'def'), toLine('allow', 'ghi')])));
        $this->assertSame([toLine('disallow', 'abc'), toLine('allow', 'def|ghi')], array_values($method([toLine('disallow', 'abc'), toLine('allow', 'def'), toLine('allow', 'ghi')])));
    }

    // TODO: respectOrder option

    public function testTestcaseSet()
    {
        if (!class_exists(TestcaseSet::class)) {
            $this->markTestSkipped('Test set module is not installed');
            return;
        }
        $set = TestcaseSet::parse(TestProfile::getYamlPath());
        if ($set->getInfo()['version'] != 1) {
            $this->markTestSkipped('Incompatible test set version');
            return;
        }
        $runner = new TestRunner();
        $runner->setTestcases($set);
        $runner->run(new Adapter\Ranvis(), [
            'maxValueLength' => 2000,
            'maxWildcards' => 100000000,
        ]);
        $status = $runner->getStatus();
        $this->assertgreaterThan(0, $status['num']['tests']);
        $this->assertSame(0, $status['num']['failures']);
        $this->assertSame(0, $status['num']['errors']);
        $this->assertSame(0, $status['num']['warnings']);
        // XXX: as of PHPUnit 5.5.4, $this->assertArraySubset() cannot show diff on failure
        $requiredFeatures = [
            'AcceptLws',
            'AcceptLwsCrlf',
            'AcceptSpaceBeforeColon',
            'ComplementLeadingSlash',
            'ComplementRecordSeparator',
            'IgnoreDirectiveCase',
            'IgnorePathTrailingSpaces',
            'IgnoreRecordSeparator',
            'IgnoreUserAgentTrailingSpaces',
            'LineCr',
            'LineCrLf',
            'LineLf',
            'LongerPathFirst',
            'PeDecodeNoMeta',
            'Wildcard',
            'WildcardDollar',
            'WildcardDollarMultiple',
        ];
        $features = array_filter($status['features']);
        foreach ($requiredFeatures as $feature) {
            $this->assertArrayHasKey($feature, $features);
            unset($features[$feature]);
        }
        $this->assertSame([], $features, "No other features are expected");
    }
}
