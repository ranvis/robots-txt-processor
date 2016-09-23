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
        ];
        parent::__construct($options);
    }

    protected function createRecord()
    {
        return new FilterRecord($this->options);
    }
}
