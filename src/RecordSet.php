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
        $this->records[$userAgent] = $record;
    }

    public function setNonGroup($record)
    {
        $this->records[self::NON_GROUP_KEY] = $record;
    }

    /**
     * Get record for the specific User-agent
     *
     * @param string $userAgent User-agent
     * @return Record|null Rules
     */
    public function getRecord(string $userAgent)
    {
        $userAgent = self::normalizeName($userAgent);
        return $this->records[$userAgent] ?? null;
    }

    public function getNonGroupRecord()
    {
        return $this->getRecord(self::NON_GROUP_KEY);
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
        if (!$record) {
            return new \EmptyIterator();
        }
        return $record->getValueIterator($directive);
    }

    public static function normalizeName(string $name) : string
    {
        return preg_replace_callback('/[A-Z]+/', function ($match) {
            return strtolower($match[0]);
        }, $name);
    }
}
