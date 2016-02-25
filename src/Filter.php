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
        $txtParser = new TxtParser($this->options);
        $maxRecords = $this->options['maxRecords'];
        $namedRecords = [];
        foreach ($txtParser->getRecordIterator($source) as $record) {
            if (!$maxRecords--) {
                break;
            }
            $spec = $txtParser->getRecordSpec($record);
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
        $txtParser = new TxtParser($this->options);
        $it = $txtParser->getRuleIterator($rules);
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
}
