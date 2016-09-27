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
            'pathMemberRegEx' => '/^(?:Dis)?Allow$/i',
            'keepTrailingSpaces' => false,
        ];
        parent::__construct($options);
    }

    public function getLineIterator(string $source)
    {
        $it = parent::getLineIterator($source);
        yield from $this->iterableFilter($it);
    }

    public function iterableFilter($it)
    {
        $remLines = $ngRemLines = $this->options['maxLines'];
        foreach ($it as $line) {
            if ($line['type'] == self::TYPE_USER_AGENT) {
                $remLines = $this->options['maxLines'];
            } elseif ($line['type'] == self::TYPE_GROUP) {
                if (--$remLines < 0) {
                    return;
                }
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
                if (--$ngRemLines < 0) {
                    return;
                }
            }
            if (!$this->options['keepTrailingSpaces']) {
                $line['value'] = rtrim($line['value'], "\t ");
            }
            yield $line;
        }
    }
}
