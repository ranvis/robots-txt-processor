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
     * Filter records on parse by User-agents.
     * Also set default user-agents on getting rules.
     *
     * @param string|array|false $userAgents User-agents to keep, false to reset and keep all
     * @param bool $fallback True to keep fallback '*' record
     * @return string|null Rules
     */
    public function setUserAgents($userAgents, bool $fallback = true)
    {
        if ($userAgents === false) {
            $this->targetUserAgents = null;
            return;
        }
        $userAgents = (array)$userAgents;
        if ($fallback) {
            $userAgents[] = '*';
        }
        $this->targetUserAgents = array_flip(array_map(function ($userAgent) {
            return $this->normalizeName($userAgent);
        }, $userAgents));
        $this->records = null; // reset
    }

    /**
     * Parse robots.txt string
     *
     * @param $source robots.txt data
     */
    public function setSource(string $source)
    {
        $txtParser = new TxtParser($this->options);
        $maxRecords = $this->options['maxRecords'];
        $this->records = [];
        foreach ($txtParser->getRecordIterator($source) as $record) {
            if (!$maxRecords--) {
                break;
            }
            $spec = $txtParser->getRecordSpec($record);
            $this->addRecord($spec);
        }
    }

    public function addRecord(array $spec)
    {
        if (!empty($spec['nonGroup'])) { // merge non-group records
            // cf. nongroupline of https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt?hl=en
            $userAgent = self::NON_GROUP_RULES;
            if (isset($this->records[$userAgent])) {
                $this->records[$userAgent] .= "\n";
            } else {
                $this->records[$userAgent] = '';
            }
            $this->records[$userAgent] .= $spec['rules'];
            return;
        }
        foreach ($spec['userAgents'] as $userAgent) {
            $rules = $spec['rules'];
            $userAgent = $this->normalizeName($userAgent);
            if (!$this->targetUserAgents || isset($this->targetUserAgents[$userAgent])) {
                $this->records[$userAgent] = $rules;
            }
        }
    }

    public function getFilteredSource($userAgents = null)
    {
        $it = $this->getRuleIterator($userAgents);
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

    public function getRuleIterator($userAgents = null)
    {
        $rules = (string)$this->getRules($userAgents);
        $txtParser = new TxtParser($this->options);
        return $txtParser->getRuleIterator($txtParser->getLineIterator($rules));
    }

    /**
     * Get rules for the first specified User-agents or '*'
     *
     * @param string|array|null $userAgents User-agents in order of preference
     * @return string|null Rules
     */
    public function getRules($userAgents = null)
    {
        if ($userAgents === null && $this->targetUserAgents !== null) {
            $userAgents = array_keys($this->targetUserAgents);
        } else {
            $userAgents = (array)$userAgents;
            $userAgents[] = '*';
        }
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
        $userAgent = $this->normalizeName($userAgent);
        return $this->records[$userAgent] ?? null;
    }

    public function getNonGroupRules()
    {
        return $this->getRawRules(self::NON_GROUP_RULES);
    }

    public function getNonGroupRuleIterator()
    {
        $rules = (string)$this->getNonGroupRules();
        $txtParser = new TxtParser($this->options);
        return $txtParser->getRuleIterator($txtParser->getLineIterator($rules));
    }

    /**
     * Get the first value of the directive
     *
     * @param string $directive directive name like Crawl-delay
     * @param string|array|null $userAgents User-agents in order of preference
     * @return ?string value of the directive
     */
    public function getValue(string $directive, $userAgents = null)
    {
        $it = $this->getValueIterator($directive, $userAgents);
        return $it->current();
    }

    public function getValueIterator(string $directive, $userAgents = null)
    {
        $it = $this->getRuleIterator($userAgents);
        return $this->getFilteredValueIterator($it, $directive);
    }

    /**
     * Get non-group directive value
     *
     * @param string $directive Name of the directive
     * @return ?string The first value of the directive or null if not defined
     */
    public function getNonGroupValue(string $directive)
    {
        $it = $this->getNonGroupIterator($directive);
        return $it->current();
    }

    public function getNonGroupIterator(string $directive)
    {
        $it = $this->getNonGroupRuleIterator();
        return $this->getFilteredValueIterator($it, $directive);
    }

    protected function getFilteredValueIterator(\Generator $it, string $directive)
    {
        $directive = strtolower($directive);
        foreach ($it as $rule) {
            if (strtolower($rule['key']) === $directive) {
                yield $rule['value'];
            }
        }
    }

    private function normalizeName($name)
    {
        return preg_replace_callback('/[A-Z]+/', function ($match) {
            return strtolower($match[0]);
        }, $name);
    }
}
