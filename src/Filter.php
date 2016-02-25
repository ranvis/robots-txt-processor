<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class Filter
{
    const NON_GROUP_RULES = '#';

    protected $options;
    protected $records;
    protected $targetUserAgents;
    private $availUserAgents;

    /**
     * @param $options Parse options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'maxRecords' => 1000,
            'maxUserAgents' => 1000,
            'maxNameLength' => 200,
            'maxLineLength' => 2000,
            'maxWildcards' => 10,
            'escapedWildcard' => true,
            'supportLws' => false,
            'pathMemberRegEx' => '/^(?i)(?:Dis)?Allow$/',
        ];
    }

    /**
     * Filter records on parse by User-agents
     *
     * @param string|array $userAgents User-agents to keep
     * @param bool $fallback True to keep fallback '*' record
     * @return string|null Rules
     */
    public function setUserAgents($userAgents, bool $fallback = true)
    {
        $userAgents = (array)$userAgents;
        if ($fallback) {
            $userAgents[] = '*';
        }
        $this->targetUserAgents = array_flip(array_map(function ($userAgent) {
            return strtolower($userAgent); // locale dependent; who cares?
        }, $userAgents));
    }

    /**
     * Parse robots.txt string
     *
     * @param $source robots.txt data
     */
    public function parse(string $source)
    {
        $this->availUserAgents = $this->options['maxUserAgents'];
        $maxRecords = $this->options['maxRecords'];
        $namedRecords = [];
        foreach ($this->getTxtIterator($source) as $record) {
            if (!$maxRecords--) {
                break;
            }
            $spec = $this->getRecordSpec($record);
            $merge = false;
            if ($spec['nonGroup']) { // merge non-group records
                // cf. nongroupline of https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt?hl=en
                $userAgent = self::NON_GROUP_RULES;
                if (isset($namedRecords[$userAgent])) {
                    $namedRecords[$userAgent] .= "\n";
                } else {
                    $namedRecords[$userAgent] = '';
                }
                $namedRecords[$userAgent] .= $spec['rules'];
                continue;
            }
            foreach ($spec['userAgents'] as $userAgent) {
                $rules = $spec['rules'];
                if (!$this->targetUserAgents || isset($this->targetUserAgents[$userAgent])) {
                    $namedRecords[$userAgent] = $rules;
                }
            }
        }
        $this->records = $namedRecords;
    }

    public function getFilteredSource($userAgents = null)
    {
        $rules = (string)$this->getRules($userAgents);
        $it = $this->getRuleIterator($rules);
        $filteredRules = 'User-agent: *';
        foreach ($it as $rule) {
            $line = $rule['key'] . ': ' . $rule['value'];
            $filteredRules .= "\n$line";
        }
        if ($nonGroupRules = $this->getNonGroupRules()) {
            $filteredRules .= "\n\n$nonGroupRules";
        }
        return $filteredRules;
    }

    public function getRuleIterator(string $rules)
    {
        $it = $this->getRecordIterator($rules);
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

    /**
     * Get rules for the first specified User-agents or '*'
     *
     * @param string|array|null $userAgents User-agents in order of preference
     * @return string|null Rules
     */
    public function getRules($userAgents = null)
    {
        if ($userAgents === null) {
            $userAgents = $this->targetUserAgents;
        }
        $userAgents = (array)$userAgents;
        $userAgents[] = '*';
        foreach ($userAgents as $userAgent) {
            $rules = $this->getRawRules($userAgent);
            if ($rules !== null) {
                break;
            }
        }
        return $rules;
    }

    /**
     * Get rules for the specified User-agent
     *
     * @param $userAgent User-agent
     * @return string|null Rules
     */
    public function getRawRules(string $userAgent)
    {
        $userAgent = strtolower($userAgent);
        return $this->records[$userAgent] ?? null;
    }

    public function getNonGroupRules()
    {
        return $this->getRawRules(self::NON_GROUP_RULES);
    }

    protected function getTxtIterator(string $source)
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

    protected function getRecordSpec(string $record)
    {
        $maxNameLength = (int)$this->options['maxNameLength'];
        $userAgents = [];
        $uaRegEx = '/\AUser-agent[ \t]*:[ \t]*(.{0,' . $maxNameLength . '})/i';
        $it = $this->getRecordIterator($record);
        $isNonGroup = true;
        foreach ($it as $line) {
            if (!preg_match($uaRegEx, $line, $match)) {
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

    protected function getRecordIterator(string $record)
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
}
