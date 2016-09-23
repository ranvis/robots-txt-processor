<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class FilterRecord extends Record
{
    protected $options;

    public function __construct(array $options = [])
    {
        $this->options = $options; // see Filter for defaults
    }

    public function addLine(array $line)
    {
        if (count($this->lines) >= $this->options['maxLines']) {
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
        parent::addLine($line);
    }
}
