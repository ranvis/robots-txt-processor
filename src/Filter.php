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
    private $availUserAgents;

    /**
     * @param $options Parse options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'maxRecords' => 1000,
            'maxWildcards' => 10,
            'escapedWildcard' => true, // set true for safety if tester treats '%2A' as a wildcard '*'
            'complementLeadingSlash' => true,
            'pathMemberRegEx' => '/^(?:Dis)?Allow$/i',
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
        $parser = new Parser($this->options);
        $maxRecords = $this->options['maxRecords'];
        $it = $parser->getRecordIterator($source, FilterRecord::class);
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
            $this->addRecord([
                'userAgents' => [self::NON_GROUP_KEY],
                'record' => $nonGroup,
            ]);
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
     * @param $userAgent User-agent
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
        $it = $this->getNonGroupIterator($directive);
        return $it->current();
    }

    public function getNonGroupIterator(string $directive)
    {
        $record = $this->getNonGroupRecord();
        return $this->getFilteredValueIterator($record, $directive);
    }

    protected function getFilteredValueIterator(RecordInterface $record, string $directive)
    {
        $directive = ucfirst(strtolower($directive));
        foreach ($record as $rule) {
            if ($rule['field'] === $directive) {
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
