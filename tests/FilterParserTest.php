<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

use Ranvis\RobotsTxt\FilterParser;

class FilterParserTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTestOptionEscapedWildcardData
     */
    public function testOptionEscapedWildcard($maxL, $maxW, $ewFlag, $clsFlag, $ktsFlag, $field, $value, $expected)
    {
        $parser = new FilterParser([
            'maxLines' => $maxL,
            'maxWildcards' => $maxW,
            'escapedWildcard' => $ewFlag,
            'complementLeadingSlash' => $clsFlag,
            'keepTrailingSpaces' => $ktsFlag,
            'pathMemberRegEx' => '/^(?:Dis)?Allow$/i',
        ]);
        $getLineType = (new ReflectionMethod($parser, 'getLineType'))->getClosure($parser);
        if (!is_array($value)) {
            $it = $parser->filter([['type' => $getLineType($field), 'field' => $field, 'value' => $value]]);
            $line = $it->current();
            $this->assertSame($expected, $line ? $line['value'] : null);
            $it->next();
        } else {
            $it = $parser->filter(array_map(function ($value) use ($getLineType) {
                return ['type' => $getLineType($value[0]), 'field' => $value[0], 'value' => $value[1]];
            }, $value));
            while ($expected) {
                $line = $it->current();
                $this->assertSame(array_shift($expected), $line ? [$line['field'], $line['value']] : null);
                $it->next();
            }
        }
        $this->assertFalse($it->valid());
    }

    public function getTestOptionEscapedWildcardData()
    {
        return [
            // maxLines
            [1000, 10, false, true, false, null, [['Disallow', '/a'], ['Disallow', '/b'], ['Disallow', '/c']], [['Disallow', '/a'], ['Disallow', '/b'], ['Disallow', '/c']]],
            [3, 10, false, true, false, null, [['Disallow', '/a'], ['Disallow', '/b'], ['Disallow', '/c']], [['Disallow', '/a'], ['Disallow', '/b'], ['Disallow', '/c']]],
            [2, 10, false, true, false, null, [['Disallow', '/a'], ['Disallow', '/b'], ['Disallow', '/c']], [['Disallow', '/a'], ['Disallow', '/b']]],
            [1, 10, false, true, false, null, [['Disallow', '/a'], ['Disallow', '/b'], ['Disallow', '/c']], [['Disallow', '/a']]],
            [0, 10, false, true, false, null, [['Disallow', '/a'], ['Disallow', '/b'], ['Disallow', '/c']], []],
            [2, 10, false, true, false, null, [['User-agent', 'a'], ['Disallow', '/a'], ['User-agent', 'b'], ['Disallow', '/b'], ['Disallow', '/c']], [['User-agent', 'a'], ['Disallow', '/a'], ['User-agent', 'b'], ['Disallow', '/b'], ['Disallow', '/c']]],
            [1, 10, false, true, false, null, [['User-agent', 'a'], ['Disallow', '/a'], ['User-agent', 'b'], ['Disallow', '/b'], ['Disallow', '/c']], [['User-agent', 'a'], ['Disallow', '/a'], ['User-agent', 'b'], ['Disallow', '/b']]],
            [1, 10, false, true, false, null, [['User-agent', 'a'], ['Disallow', '/a'], ['Disallow', '/b'], ['User-agent', 'b'], ['Disallow', '/c']], [['User-agent', 'a'], ['Disallow', '/a'], ['User-agent', 'b'], ['Disallow', '/c']]],
            // maxWildcards
            [1000, 5, true, true, false, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1000, 4, true, true, false, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1000, 1, true, true, false, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1000, 0, true, true, false, 'Unknown', '*****v****a***l**u*e', '*****v****a***l**u*e'],
            [1000, 5, true, true, false, 'Allow', '*****v****a***l**u*e', '*v*a*l*u*e'],
            [1000, 4, true, true, false, 'Allow', '*****v****a***l**u*e', null],
            [1000, 4, true, true, false, 'Allow', '*****v****a***l**ue', '*v*a*l*ue'],
            // escapedWildcard
            [1000, 3, true, true, false, 'Disallow', '*/%2a%2A/%2a%2A', '*/*/*'],
            [1000, 3, true, true, false, 'Disallow', '*/%2a%2A*%2a%2A', '*/*'],
            [1000, 2, true, true, false, 'Disallow', '*/%2a%2A/%2a%2A', null],
            [1000, 2, true, true, false, 'Disallow', '*/%2a%2A*%2a%2A', '*/*'],
            [1000, 3, false, true, false, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [1000, 3, false, true, false, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [1000, 2, false, true, false, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [1000, 2, false, true, false, 'Disallow', '*/%2a%2A*%2a%2A', '*/%2a%2A*%2a%2A'],
            [1000, 1, false, true, false, 'Disallow', '*/%2a%2A/%2a%2A', '*/%2a%2A/%2a%2A'],
            [1000, 0, false, true, false, 'Disallow', '*/%2a%2A/%2a%2A', null],
            // complementLeadingSlash
            [1000, 3, true, true, false, 'Disallow', '', ''],
            [1000, 3, true, true, false, 'Disallow', '*', '*'],
            [1000, 3, true, true, false, 'Disallow', '*foo', '*foo'],
            [1000, 3, true, true, false, 'Disallow', 'foo*bar', '/foo*bar'],
            [1000, 3, true, true, false, 'Disallow', 'foo$', 'foo$'],
            [1000, 3, true, true, false, 'Disallow', '%2a', '*'],
            [1000, 3, false, true, false, 'Disallow', '%2a', '/%2a'],
            [1000, 3, false, true, false, 'Disallow', 'foo', '/foo'],
            [1000, 3, false, true, false, 'Sitemap', 'foo', 'foo'], // not path, and absoluteURL is expected
            [1000, 3, true, false, false, 'Disallow', '', ''],
            [1000, 3, true, false, false, 'Disallow', '*', '*'],
            [1000, 3, true, false, false, 'Disallow', '*foo', '*foo'],
            [1000, 3, true, false, false, 'Disallow', 'foo*bar', null],
            [1000, 3, true, false, false, 'Disallow', 'foo$', 'foo$'],
            [1000, 3, true, false, false, 'Disallow', '%2a', '*'],
            [1000, 3, false, false, false, 'Disallow', '%2a', null],
            [1000, 3, false, false, false, 'Disallow', 'foo', null],
            // keepTrailingSpaces
            [1000, 3, false, false, false, 'Disallow', "/path \t ", '/path'],
            [1000, 3, false, false, true, 'Disallow', "/path \t ", "/path \t "],
        ];
    }
}
