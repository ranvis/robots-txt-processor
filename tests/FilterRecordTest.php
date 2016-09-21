<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

use Ranvis\RobotsTxt\FilterRecord;

class FilterRecordTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestOptionEscapedWildcardData
     */
    public function testOptionEscapedWildcard($max, $ewFlag, $clsFlag, $field, $value, $expected)
    {
        $filter = new FilterRecord([
            'maxWildcards' => $max,
            'escapedWildcard' => $ewFlag,
            'complementLeadingSlash' => $clsFlag,
            'pathMemberRegEx' => '/^(?:Dis)?Allow$/i',
        ]);
        $filter->addLine(['field' => $field, 'value' => $value]);
        $line = $filter->getIterator()->current();
        $this->assertSame($expected, $line ? $line['value'] : null);
    }

    public function getTestOptionEscapedWildcardData()
    {
        return [
            // maxWildcards
            [5, true, true, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [4, true, true, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1, true, true, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [0, true, true, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [5, true, true, 'Allow', '*****v****a***l**u*e', '*v*a*l*u*e'],
            [4, true, true, 'Allow', '*****v****a***l**u*e', null],
            [4, true, true, 'Allow', '*****v****a***l**ue', '*v*a*l*ue'],
            // escapedWildcard
            [3, true, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/*/*'],
            [3, true, true, 'Disallow', '*/%2a%2A*%2a%2A', '*/*'],
            [2, true, true, 'Disallow', '*/%2a%2A/%2a%2A', null],
            [2, true, true, 'Disallow', '*/%2a%2A*%2a%2A', '*/*'],
            [3, false, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [3, false, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [2, false, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [2, false, true, 'Disallow', '*/%2a%2A*%2a%2A', '*/%2a%2A*%2a%2A'],
            [1, false, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [0, false, true, 'Disallow', '*/%2a%2A/%2a%2A', null],
            // complementLeadingSlash
            [3, true, true, 'Disallow', '', ''],
            [3, true, true, 'Disallow', '*', '*'],
            [3, true, true, 'Disallow', '*foo', '*foo'],
            [3, true, true, 'Disallow', 'foo*bar', '/foo*bar'],
            [3, true, true, 'Disallow', 'foo$', 'foo$'],
            [3, true, true, 'Disallow', '%2a', '*'],
            [3, false, true, 'Disallow', '%2a', '/%2a'],
            [3, false, true, 'Disallow', 'foo', '/foo'],
            [3, false, true, 'Sitemap', 'foo', 'foo'], // not path, and absoluteURL is expected
            [3, true, false, 'Disallow', '', ''],
            [3, true, false, 'Disallow', '*', '*'],
            [3, true, false, 'Disallow', '*foo', '*foo'],
            [3, true, false, 'Disallow', 'foo*bar', null],
            [3, true, false, 'Disallow', 'foo$', 'foo$'],
            [3, true, false, 'Disallow', '%2a', '*'],
            [3, false, false, 'Disallow', '%2a', null],
            [3, false, false, 'Disallow', 'foo', null],
        ];
    }
}
