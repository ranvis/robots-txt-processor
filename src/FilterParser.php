<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class FilterParser extends Parser
{
    public function __construct(array $options = [])
    {
        $options += [
            'maxLines' => 1000,
            'keepTrailingSpaces' => false,
            'maxWildcards' => 10,
            'escapedWildcard' => true, // default is true for safety for unknown tester may treat '%2A' as a wildcard '*'
            'complementLeadingSlash' => true,
            'pathMemberRegEx' => '/^(?:Dis)?Allow$/i',
        ];
        parent::__construct($options);
    }

    public function getLineIterator(string $source)
    {
        $it = parent::getLineIterator($source);
        yield from $this->filter($it);
    }

    public function filter($it)
    {
        $remLines = [
            self::TYPE_GROUP => $this->options['maxLines'],
            self::TYPE_NON_GROUP => $this->options['maxLines'],
        ];
        foreach ($it as $line) {
            if (isset($remLines[$line['type']]) && --$remLines[$line['type']] < 0) {
                continue;
            }
            if ($line['type'] == self::TYPE_USER_AGENT) {
                $remLines[self::TYPE_GROUP] = $this->options['maxLines'];
            } elseif ($line['type'] == self::TYPE_GROUP) {
                if (preg_match($this->options['pathMemberRegEx'], $line['field'])) {
                    $path = $line['value'];
                    if ($this->options['escapedWildcard']) {
                        $path = preg_replace('/%2[Aa]/', '*', $path);
                    }
                    if (strlen($path) && !preg_match('#\A[/*]|\$\z#', $path)) {
                        if (!$this->options['complementLeadingSlash']) {
                            return;
                        }
                        $path = "/$path";
                    }
                    $path = preg_replace('/\*+/', '*', $path, -1, $count);
                    if ($count > $this->options['maxWildcards']) {
                        return;
                    }
                    $line['value'] = $path;
                }
            } elseif ($line['type'] == self::TYPE_NON_GROUP) {
                // do nothing
            }
            if (!$this->options['keepTrailingSpaces']) {
                $line['value'] = rtrim($line['value'], "\t ");
            }
            yield $line;
        }
    }
}
