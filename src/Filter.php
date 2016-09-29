<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class Filter
{
    protected $options;
    protected $recordSet;
    protected $targetUserAgents;

    /**
     * @param array $options Parser options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'maxRecords' => 1000,
        ];
    }

    /**
     * Filter records on parse by User-agents.
     * Also set default user-agents on getting record.
     *
     * @param string|array|false $userAgents User-agents to keep, false to clear filter to keep all
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
        $this->recordSet = null; // reset
    }

    /**
     * Parse robots.txt string
     *
     * @param string|\Traversable $source robots.txt data or record iterator
     * @return RecordSet Filtered records
     */
    public function getRecordSet($source) : RecordSet
    {
        if (is_string($source)) {
            $parser = new FilterParser($this->options);
            $it = $parser->getRecordIterator($source);
        } else {
            $it = $source;
        }
        $maxRecords = $this->options['maxRecords'];
        $this->recordSet = new RecordSet();
        foreach ($it as $spec) {
            if (!$maxRecords--) {
                while ($it->valid()) {
                    $it->next();
                }
                break;
            }
            foreach ($spec['userAgents'] as $userAgent) {
                $userAgent = $this->normalizeName($userAgent);
                if (!$this->targetUserAgents || isset($this->targetUserAgents[$userAgent])) {
                    $this->recordSet->add($userAgent, $spec['record']);
                }
            }
        }
        $ordering = $this->targetUserAgents ? array_flip(array_keys($this->targetUserAgents)) : [];
        $ordering['*'] = 1000000; // * is latter if targetUserAgents is set
        $this->recordSet->sort(function ($a, $b) use ($ordering) {
            return ($ordering[$a] ?? 2000000) <=> ($ordering[$b] ?? 2000000);
        });
        $nonGroup = $it->getReturn();
        if ($nonGroup) {
            $this->recordSet->setNonGroupRecord($nonGroup);
        }
        return $this->recordSet;
    }

    private function normalizeName(string $name) : string
    {
        return RecordSet::normalizeName($name);
    }
}
