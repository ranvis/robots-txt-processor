<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class TxtParser
{
    protected $options;
    private $availUserAgents;
    private $userAgentLineRegEx;

    /**
     * @param $options Parse options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'maxUserAgents' => 1000,
            'maxNameLength' => 200,
            'maxLineLength' => 2000,
            'maxWildcards' => 10,
            'escapedWildcard' => true, // set true for safety if tester treats '%2A' as a wildcard '*'
            'supportLws' => false,
            'pathMemberRegEx' => '/^(?i)(?:Dis)?Allow$/',
        ];
        $this->availUserAgents = $this->options['maxUserAgents'];
        $maxNameLength = (int)$this->options['maxNameLength'];
        $this->userAgentLineRegEx = '/\AUser-agent[ \t]*:[ \t]*(.{0,' . $maxNameLength . '})(.*)\z/i';
    }

    protected function replaceLws(string $source)
    {
        if ($this->options['supportLws']) {
            $lws = '(?:(?:\x0d\x0a?|\x0a)[ \t]+|[ \t]*)'; // *(LWS-ish | WSP)
            $maxLen = $this->options['maxLineLength'];
            if (preg_match('/^[ \t]+:|:$/m', $source)) {
                $source = preg_replace("/^[ \\t]*([A-Za-z-]{1,$maxLen})$lws:$lws(?=[^ \\t#]|$)/m", '\1: ', $source);
            }
        }
        return $source;
    }

    public function getRecordIterator(string $source)
    {
        $source = $this->replaceLws($source);
        $source = ltrim($source, "\x0a\x0d");
        while (strlen($source)) {
            $records = preg_split('/(?:\x0d\x0a?+|\x0a){2,}/', $source, 2); // 2 newlines of any type
            yield array_shift($records);
            $source = $records ? array_shift($records) : '';
        }
    }

    public function getRecordSpec(string $record)
    {
        $record = $this->replaceLws($record);
        $userAgents = [];
        $it = $this->getLineIterator($record);
        $isNonGroup = true;
        foreach ($it as $line) {
            if (!preg_match($this->userAgentLineRegEx, $line, $match)) {
                $it->send(true);
                break;
            }
            $isNonGroup = false;
            if ($this->availUserAgents > 0 && !strlen($match[2])) {
                $this->availUserAgents--;
                $userAgents[$match[1]] = true; // not normalized
            }
        }
        $record = $it->getReturn();
        return [
            'nonGroup' => $isNonGroup,
            'userAgents' => array_map(function ($value) {
                return (string)$value;
            }, array_keys($userAgents)),
            'rules' => $record,
        ];
    }

    public function getLineIterator(string $record)
    {
        $record = $this->replaceLws($record);
        $record = rtrim($record, "\x0a\x0d");
        $maxLineLength = $this->options['maxLineLength'];
        for ($offset = 0; !isset($match[2]); ) {
            if (!preg_match('/[\x0a\x0d]+/', $record, $match, PREG_OFFSET_CAPTURE, $offset)) {
                $match = ['', strlen($record), true];
            } else {
                $match = $match[0];
            }
            if ($match[1] <= $maxLineLength) {
                $line = substr($record, $offset, $match[1] - $offset);
                $line = preg_replace('/[ \t]*#.*/', '', $line); // remove comment
                $line = ltrim($line, " \t"); // remove leading spaces
                // trailing spaces are significant if no comment
                if (strlen($line)) {
                    $msg = (yield $line);
                    if ($msg === true) {
                        break;
                    }
                }
            }
            $offset = $match[1] + strlen($match[0]);
        }
        return substr($record, $offset);
    }

    public function getRuleIterator($lines)
    {
        $pathMemberRegEx = $this->options['pathMemberRegEx'];
        $maxWildcards = $this->options['maxWildcards'];
        $escapedWildcard = $this->options['escapedWildcard'];
        foreach ($lines as $line) {
            if (!preg_match('/\A(?<key>[^:]+?)[ \t]*:[ \t]*(?<value>.*)/', $line, $match)) {
                continue;
            }
            if (preg_match($pathMemberRegEx, $match['key'])) {
                $path = $match['value'];
                if ($escapedWildcard) {
                    $path = preg_replace('/%2a/i', '*', $path);
                }
                if (strlen($path) && !preg_match('#\A[/*]#', $path)) {
                    continue;
                }
                $path = preg_replace('/\*+/', '*', $path, -1, $count);
                if ($count > $maxWildcards) {
                    continue;
                }
                $match['value'] = $path;
            }
            yield $match;
        }
    }
}
