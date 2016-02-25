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
            'escapedWildcard' => true,
            'supportLws' => false,
            'pathMemberRegEx' => '/^(?i)(?:Dis)?Allow$/',
        ];
        $this->availUserAgents = $this->options['maxUserAgents'];
        $maxNameLength = (int)$this->options['maxNameLength'];
        $this->userAgentLineRegEx = '/\AUser-agent[ \t]*:[ \t]*(.{0,' . $maxNameLength . '})/i';
    }

    public function getRecordIterator(string $source)
    {
        if ($this->options['supportLws']) {
            $source = preg_replace('/\x0d\x0a[ \t]+/s', ' ', $source);
        }
        while (strlen($source)) {
            $records = preg_split('/(?:\x0d\x0a?|\x0a){2,}/s', $source, 2);
            yield array_shift($records);
            $source = $records ? array_shift($records) : '';
        }
    }

    public function getRecordSpec(string $record)
    {
        $userAgents = [];
        $it = $this->getLineIterator($record);
        $isNonGroup = true;
        foreach ($it as $line) {
            if (!preg_match($this->userAgentLineRegEx, $line, $match)) {
                $it->send(true);
                break;
            }
            $isNonGroup = false;
            if ($this->availUserAgents > 0) {
                $this->availUserAgents--;
                $userAgents[strtolower($match[1])] = true;
            }
        }
        $record = $it->getReturn();
        return [
            'nonGroup' => $isNonGroup,
            'userAgents' => array_keys($userAgents),
            'rules' => $record,
        ];
    }

    public function getLineIterator(string $record)
    {
        $record = rtrim($record, "\x0a\x0d");
        $maxLineLength = $this->options['maxLineLength'];
        while (strlen($record)) {
            $lines = preg_split('/(?:\x0d\x0a?|\x0a)+/s', $record, 2);
            $line = $lines[0];
            if (strlen($line) <= $maxLineLength) {
                $line = preg_replace('/[ \t]*#.*$/s', '', $line); // remove comment
                $line = ltrim($line, " \t"); // remove leading spaces
                if (strlen($line)) {
                    $msg = (yield $line);
                    if ($msg === true) {
                        break;
                    }
                }
            }
            $record = count($lines) > 1 ? $lines[1] : '';
        }
        return $record;
    }

    public function getRuleIterator(string $rules)
    {
        $it = $this->getLineIterator($rules);
        $pathMemberRegEx = $this->options['pathMemberRegEx'];
        $maxWildcards = $this->options['maxWildcards'];
        $escapedWildcard = $this->options['escapedWildcard'];
        foreach ($it as $line) {
            if (!preg_match('/\A(?<key>[^:]+?)[ \t]*:[ \t]*(?<value>.*)/', $line, $match)) {
                continue;
            }
            if (preg_match($pathMemberRegEx, $match['key'])) {
                $path = $match['value'];
                if ($escapedWildcard) {
                    $path = preg_replace('/%2a/i', '*', $path);
                }
                if (!preg_match('#\A[/*]#', $path)) {
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
