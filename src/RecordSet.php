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

    public function addNonGroup($record)
    {
        $this->records[self::NON_GROUP_KEY] = $record;
    }

    /**
     * Get record for the specific User-agent
     *
     * @param string $userAgent User-agent
     * @return RecordInterface|null Rules
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

    public static function getFilteredValueIterator(RecordInterface $record = null, string $directive)
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

    public static function normalizeName(string $name) : string
    {
        return preg_replace_callback('/[A-Z]+/', function ($match) {
            return strtolower($match[0]);
        }, $name);
    }
}
