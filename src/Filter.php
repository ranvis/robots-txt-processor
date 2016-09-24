<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class Filter
{
    const NON_GROUP_KEY = '#';

    protected $options;
    protected $records;
    protected $targetUserAgents;

    /**
     * @param array $options Parser options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'maxRecords' => 1000,
            'maxWildcards' => 10,
            'escapedWildcard' => true, // set true for safety if tester treats '%2A' as a wildcard '*'
            'complementLeadingSlash' => true,
            'keepTrailingSpaces' => false,
        ];
    }

    /**
     * Filter records on parse by User-agents.
     * Also set default user-agents on getting rules.
     *
     * @param string|array|false $userAgents User-agents to keep, false to reset and keep all
     * @param bool $fallback True to keep fallback '*' record
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
     * @param string|\Traversable $source robots.txt data or record iterator
     */
    public function setSource($source)
    {
        if (is_string($source)) {
            $parser = new FilterParser($this->options);
            $it = $parser->getRecordIterator($source);
        } else {
            $it = $source;
        }
        $maxRecords = $this->options['maxRecords'];
        $this->records = [];
        foreach ($it as $spec) {
            if (!$maxRecords--) {
                while ($it->valid()) {
                    $it->next();
                }
                break;
            }
            $this->addRecord($spec);
        }
        $nonGroup = $it->getReturn();
        if ($nonGroup) {
            $this->records[self::NON_GROUP_KEY] = $nonGroup;
        }
    }

    protected function addRecord(array $spec)
    {
        foreach ($spec['userAgents'] as $userAgent) {
            $userAgent = $this->normalizeName($userAgent);
            if (!$this->targetUserAgents || isset($this->targetUserAgents[$userAgent])) {
                $this->records[$userAgent] = $spec['record'];
            }
        }
    }

    public function getFilteredSource($userAgents = null)
    {
        $filteredSource = "User-agent: *\x0d\x0a";
        $filteredSource .= $this->getRecord($userAgents); // may be null
        $nonGroupRecord = (string)$this->getNonGroupRecord();
        if ($nonGroupRecord !== '') {
            if ($filteredSource !== '') {
                $filteredSource .= "\x0d\x0a";
            }
            $filteredSource .= $nonGroupRecord;
        }
        return $filteredSource;
    }

    /**
     * Get record for the first specified User-agents or '*'
     *
     * @param string|array|null $userAgents User-agents in order of preference
     * @return RecordInterface|null Record of lines
     */
    public function getRecord($userAgents = null)
    {
        if ($userAgents === null && $this->targetUserAgents !== null) {
            $userAgents = array_keys($this->targetUserAgents);
        } else {
            $userAgents = (array)$userAgents;
            $userAgents[] = '*';
        }
        $record = null;
        foreach ($userAgents as $userAgent) {
            $record = $this->getRawRecord($userAgent);
            if ($record !== null) {
                break;
            }
        }
        return $record;
    }

    /**
     * Get record for the specified User-agent
     *
     * @param string $userAgent User-agent
     * @return RecordInterface|null Rules
     */
    protected function getRawRecord(string $userAgent)
    {
        $userAgent = $this->normalizeName($userAgent);
        return $this->records[$userAgent] ?? null;
    }

    public function getNonGroupRecord()
    {
        return $this->getRawRecord(self::NON_GROUP_KEY);
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
        $record = $this->getRecord($userAgents);
        return $this->getFilteredValueIterator($record, $directive);
    }

    /**
     * Get non-group directive value
     *
     * @param string $directive Name of the directive
     * @return ?string The first value of the directive or null if not defined
     */
    public function getNonGroupValue(string $directive)
    {
        $it = $this->getNonGroupValueIterator($directive);
        return $it->current();
    }

    public function getNonGroupValueIterator(string $directive)
    {
        $record = $this->getNonGroupRecord();
        return $this->getFilteredValueIterator($record, $directive);
    }

    protected function getFilteredValueIterator(RecordInterface $record = null, string $directive)
    {
        if ($record) {
            $directive = ucfirst(strtolower($directive));
            foreach ($record as $rule) {
                if ($rule['field'] === $directive) {
                    yield $rule['value'];
                }
            }
        }
    }

    private function normalizeName($name)
    {
        if (!$this->options['keepTrailingSpaces']) {
            $name = rtrim($name, "\t ");
        }
        return preg_replace_callback('/[A-Z]+/', function ($match) {
            return strtolower($match[0]);
        }, $name);
    }
}
