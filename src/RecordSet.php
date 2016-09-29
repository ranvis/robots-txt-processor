<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class RecordSet
{
    const NON_GROUP_KEY = '#';

    protected $records = [];

    public function __construct()
    {
    }

    public function add(string $userAgent, $record)
    {
        $this->records[self::normalizeName($userAgent)] = $record;
    }

    public function setNonGroupRecord($record)
    {
        $this->records[self::NON_GROUP_KEY] = $record;
    }

    public function extract($userAgents = null) : RecordSet
    {
        $filteredSet = new static();
        $record = $this->getRecord($userAgents, false);
        if ($record) {
            $filteredSet->add('*', $record);
        }
        $nonGroupRecord = $this->getNonGroupRecord(false);
        if ($nonGroupRecord) {
            $filteredSet->setNonGroupRecord($nonGroupRecord);
        }
        return $filteredSet;
    }

    /**
     * Get record for the first specified User-agents or '*'
     *
     * @param string|array|null $userAgents User-agents in order of preference
     * @paran bool $dummy true to return dummy Record instead of null
     * @return Record|null Record of lines
     */
    public function getRecord($userAgents = null, bool $dummy = true)
    {
        if ($userAgents === null) {
            $userAgents = array_filter(array_keys($this->records), function ($userAgent) {
                return $userAgent !== self::NON_GROUP_KEY;
            });
        } else {
            $userAgents = (array)$userAgents;
            $userAgents[] = '*';
        }
        $record = null;
        foreach ($userAgents as $userAgent) {
            $userAgent = self::normalizeName($userAgent);
            $record = $this->records[$userAgent] ?? null;
            if ($record !== null) {
                break;
            }
        }
        if ($record === null && $dummy) {
            $record = new Record();
        }
        return $record;
    }

    public function getNonGroupRecord(bool $dummy = true)
    {
        $record = $this->records[self::NON_GROUP_KEY] ?? null;
        if ($record === null && $dummy) {
            $record = new Record();
        }
        return $record;
    }

    public function __toString()
    {
        $textRecords = [];
        foreach ($this->records as $userAgent => $record) {
            if ($userAgent === self::NON_GROUP_KEY) {
                continue;
            }
            $textRecords[] = "User-agent: $userAgent\x0d\x0a" . $record;
        }
        $nonGroupRecord = (string)$this->getNonGroupRecord();
        if ($nonGroupRecord !== '') {
            $textRecords[] = $nonGroupRecord;
        }
        return implode("\x0d\x0a", $textRecords);
    }

    public function sort(Callable $cmpProc)
    {
        uksort($this->records, $cmpProc);
    }

    public static function normalizeName(string $name) : string
    {
        return preg_replace_callback('/[A-Z]+/', function ($match) {
            return strtolower($match[0]);
        }, $name);
    }
}
