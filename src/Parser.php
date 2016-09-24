<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class Parser
{
    protected $options;
    protected $groupedDirectives = ['(?:Dis)?Allow|Crawl-delay|-ignored-'];
    protected $groupMemberRegEx;

    const TYPE_USER_AGENT = 1;
    const TYPE_GROUP = 2;
    const TYPE_NON_GROUP = 3;

    /**
     * @param array $options Parser options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options + [
            'maxUserAgents' => 1000,
            'maxDirectiveLength' => 32,
            'maxNameLength' => 200,
            'maxValueLength' => 2000,
            'userAgentRegEx' => '/^User-?agent$/i',
        ];
        $this->flushGroupDirective();
    }

    public function registerGroupDirective(string $directive)
    {
        $this->groupedDirectives[] = preg_quote($directive, '/');
        $this->flushGroupDirective();
    }

    public function flushGroupDirective()
    {
        $this->groupMemberRegEx = '/^(?:' . implode('|', $this->groupedDirectives) . ')$/i';
    }

    public function getLineIterator(string $source)
    {
        $lws = '(?:(?:\x0d\x0a?|\x0a)[ \t]+|[ \t]*)'; // *(LWS-ish | WSP)
        $maxDirectiveLength = (int)$this->options['maxDirectiveLength'];
        $maxValueLength = (int)$this->options['maxValueLength'];
        $pattern = "/(*ANYCRLF)^[ \t]*(?:(?<field>[A-Za-z-]{1,$maxDirectiveLength})$lws:$lws(?<value>[^\x0d\x0a \t#]*+[^\x0d\x0a#]*?))?(?:[ \t]*#[^\x0d\x0a]*)?(?<end>\x0d\x0a?|\x0a|\z)/m";
        // trailing spaces are significant if no comment
        $limit = strlen($source);
        for ($offset = 0; $offset < $limit; ) {
            if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE, $offset)) {
                preg_match('/[\x0d\x0a]+|\z/', $source, $match, PREG_OFFSET_CAPTURE, $offset);
                $offset = $match[0][1] + strlen($match[0][0]);
                continue;
            }
            if (isset($match['field']) && $match['field'][1] >= 0) { // [0] is always string; see PHP bug #51881, #61780
                $field = $match['field'][0];
                $value = $match['value'][0];
                if (strlen($value) > $maxValueLength) {
                    $field = '-ignored-';
                    $value = (string)strlen($value);
                }
                yield [
                    'field' => ucfirst(strtolower($field)),
                    'value' => $value,
                ];
            }
            $offset = $match['end'][1] + strlen($match['end'][0]);
        }
    }

    protected function getLineType(array $line)
    {
        if (preg_match($this->groupMemberRegEx, $line['field'])) {
            $type = self::TYPE_GROUP;
        } elseif (preg_match($this->options['userAgentRegEx'], $line['field'])) {
            $type = self::TYPE_USER_AGENT;
        } else {
            $type = self::TYPE_NON_GROUP;
        }
        return $type;
    }

    protected function createRecord()
    {
        return new Record($this->options);
    }

    /**
     * @param \Traversable|array|string $it Parsed lines or raw source string
     * @return \Iterator Record iterator
     */
    public function getRecordIterator($it)
    {
        $availUserAgents = (int)$this->options['maxUserAgents'];
        $maxNameLength = (int)$this->options['maxNameLength'];
        if (is_string($it)) {
            $it = $this->getLineIterator($it);
        }
        $currentAgents = $currentRecord = null;
        $nonGroupRecord = $this->createRecord();
        foreach ($it as $line) {
            $type = $this->getLineType($line);
            if ($type === self::TYPE_USER_AGENT) {
                if ($currentRecord) {
                    yield [
                        'userAgents' => $currentAgents,
                        'record' => $currentRecord,
                    ];
                    $currentAgents = $currentRecord = null;
                }
                if (--$availUserAgents >= 0) {
                    $currentAgents[] = substr($line['value'], 0, $maxNameLength);
                }
            } elseif ($type === self::TYPE_GROUP) {
                if (!$currentAgents) {
                    continue;
                }
                if (!$currentRecord) {
                    $currentRecord = $this->createRecord();
                }
                $currentRecord->addLine($line);
            } elseif ($type === self::TYPE_NON_GROUP) {
                $nonGroupRecord->addLine($line);
            }
        }
        if ($currentRecord) {
            yield [
                'userAgents' => $currentAgents,
                'record' => $currentRecord,
            ];
        }
        return $nonGroupRecord;
    }
}
