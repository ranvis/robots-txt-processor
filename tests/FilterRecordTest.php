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
    public function testOptionEscapedWildcard($maxL, $maxW, $ewFlag, $clsFlag, $field, $value, $expected)
    {
        $filter = new FilterRecord([
            'maxLines' => $maxL,
            'maxWildcards' => $maxW,
            'escapedWildcard' => $ewFlag,
            'complementLeadingSlash' => $clsFlag,
            'pathMemberRegEx' => '/^(?:Dis)?Allow$/i',
        ]);
        if (!is_array($value)) {
            $filter->addLine(['field' => $field, 'value' => $value]);
            $it = $filter->getIterator();
            $line = $it->current();
            $this->assertSame($expected, $line ? $line['value'] : null);
            $it->next();
        } else {
            while ($value) {
                $filter->addLine(['field' => $field, 'value' => array_shift($value)]);
            }
            $it = $filter->getIterator();
            while ($expected) {
                $line = $it->current();
                $this->assertSame(array_shift($expected), $line ? $line['value'] : null);
                $it->next();
            }
        }
        $this->assertFalse($it->valid());
    }

    public function getTestOptionEscapedWildcardData()
    {
        return [
            // maxLines
            [1000, 10, false, true, 'Disallow', ['/a', '/b', '/c'], ['/a', '/b', '/c']],
            [3, 10, false, true, 'Disallow', ['/a', '/b', '/c'], ['/a', '/b', '/c']],
            [2, 10, false, true, 'Disallow', ['/a', '/b', '/c'], ['/a', '/b']],
            [1, 10, false, true, 'Disallow', ['/a', '/b', '/c'], ['/a']],
            [0, 10, false, true, 'Disallow', ['/a', '/b', '/c'], []],
            // maxWildcards
            [1000, 5, true, true, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1000, 4, true, true, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1000, 1, true, true, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1000, 0, true, true, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1000, 5, true, true, 'Allow', '*****v****a***l**u*e', '*v*a*l*u*e'],
            [1000, 4, true, true, 'Allow', '*****v****a***l**u*e', null],
            [1000, 4, true, true, 'Allow', '*****v****a***l**ue', '*v*a*l*ue'],
            // escapedWildcard
            [1000, 3, true, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/*/*'],
            [1000, 3, true, true, 'Disallow', '*/%2a%2A*%2a%2A', '*/*'],
            [1000, 2, true, true, 'Disallow', '*/%2a%2A/%2a%2A', null],
            [1000, 2, true, true, 'Disallow', '*/%2a%2A*%2a%2A', '*/*'],
            [1000, 3, false, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [1000, 3, false, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [1000, 2, false, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [1000, 2, false, true, 'Disallow', '*/%2a%2A*%2a%2A', '*/%2a%2A*%2a%2A'],
            [1000, 1, false, true, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [1000, 0, false, true, 'Disallow', '*/%2a%2A/%2a%2A', null],
            // complementLeadingSlash
            [1000, 3, true, true, 'Disallow', '', ''],
            [1000, 3, true, true, 'Disallow', '*', '*'],
            [1000, 3, true, true, 'Disallow', '*foo', '*foo'],
            [1000, 3, true, true, 'Disallow', 'foo*bar', '/foo*bar'],
            [1000, 3, true, true, 'Disallow', 'foo$', 'foo$'],
            [1000, 3, true, true, 'Disallow', '%2a', '*'],
            [1000, 3, false, true, 'Disallow', '%2a', '/%2a'],
            [1000, 3, false, true, 'Disallow', 'foo', '/foo'],
            [1000, 3, false, true, 'Sitemap', 'foo', 'foo'], // not path, and absoluteURL is expected
            [1000, 3, true, false, 'Disallow', '', ''],
            [1000, 3, true, false, 'Disallow', '*', '*'],
            [1000, 3, true, false, 'Disallow', '*foo', '*foo'],
            [1000, 3, true, false, 'Disallow', 'foo*bar', null],
            [1000, 3, true, false, 'Disallow', 'foo$', 'foo$'],
            [1000, 3, true, false, 'Disallow', '%2a', '*'],
            [1000, 3, false, false, 'Disallow', '%2a', null],
            [1000, 3, false, false, 'Disallow', 'foo', null],
        ];
    }
}
