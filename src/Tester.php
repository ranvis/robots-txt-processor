<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

namespace Ranvis\RobotsTxt;

class Tester extends Filter
{
    private $rules;

    /**
     * @param array $options Options
     */
    public function __construct(array $options = [])
    {
        $options += [
            'respectOrder' => false,
            'ignoreForbidden' => false,
            'escapedWildcard' => false,
        ];
        parent::__construct($options);
    }

    /**
     * @param int $code HTTP response code of robots.txt
     * @return bool True if allowed to crawl
     */
    public function isResponseCodeAllowed(int $code)
    {
        if ($code <= 299) {
            $allowed = true; // like 204 No Content
        } elseif ($code <= 399) {
            $allowed = false; // exclude 3xx for too many redirections
        } elseif ($code <= 499) {
            // like 404 Not Found and 410 Gone
            if ($this->options['ignoreForbidden']) {
                $allowed = true;
            } else {
                // exclude 401 Unauthorized, 403 Forbidden, and others
                $allowed = !in_array($code, [401, 403], true);
            }
        } else {
            $allowed = false; // exclude 5xx for server error, and others
        }
        return $allowed;
    }

    /**
     * Set robots.txt source data from response code
     *
     * @param int $code HTTP response code of robots.txt
     */
    public function setResponseCode(int $code)
    {
        $allowed = $this->isResponseCodeAllowed($code);
        $this->setSource($allowed ? '' : "User-agent: *\nDisallow: /");
    }

    public function setSource($source)
    {
        parent::setSource($source);
        $this->rules = null;
    }

    /**
     * Test if the path is allowed to crawl
     *
     * @param string $path URL path to test
     * @param string|array|null $userAgents User-agents in order of preference
     * @return bool true if allowed to crawl
     */
    public function isAllowed(string $targetPath, $userAgents = null)
    {
        if ($targetPath[0] != '/') {
            throw new \InvalidArgumentException('Path should be started with slash: ' . $targetPath);
        }
        $rules = $this->getPathRules($userAgents);
        $targetPath = $this->normalizePath($targetPath, false);
        foreach ($rules as $rule) {
            if (preg_match("\x01\\A(?:" . $rule['value'] . ")\x01s", $targetPath)) {
                return $rule['field'] === 'allow';
            }
        }
        return true;
    }

    protected function getPathRules($userAgents = null)
    {
        $rules = $this->rules;
        if ($rules === null || $userAgents !== null) {
            $it = $this->getRecord($userAgents);
            $rules = [];
            foreach ($it ?: [] as $rule) {
                $field = lcfirst($rule['field']);
                if (!in_array($field, ['allow', 'disallow'], true)) {
                    continue;
                }
                $path = $this->normalizePath($rule['value'], true);
                if ($path === '' && $field === 'disallow') {
                    $field = 'allow';
                }
                $rules[] = [
                    'field' => $field,
                    'value' => $path,
                ];
            }
            if (!$this->options['respectOrder']) {
                $rules = $this->sortRules($rules);
            }
            $rules = $this->compactRules($rules);
            if ($userAgents === null) {
                $this->rules = $rules;
            }
        }
        return $rules;
    }

    protected function normalizePath(string $path, bool $isRule)
    {
        $path = preg_replace_callback('/%([0-9A-Fa-f]{2})/', function ($match) {
            $ch = chr(hexdec($match[1]));
            if (strpos('%:/?#[]@!$&\'()*+,;=', $ch) !== false) { // keep %, meta *$, comment #, and RFC 3986 "reserved" encoded and differentiate from the raw character of it
                return '%' . strtoupper($match[1]);
            }
            return $ch;
        }, $path);
        $path = preg_replace_callback('/[\x00-\x1f]|%(?![0-9A-Fa-f]{2})/', function ($match) {
            return sprintf('%%%02X', ord($match[0]));
        }, $path);
        if ($isRule) {
            $metaPlaceholderMap = null;
            $path = $this->preparePathRuleRegEx($path, $metaPlaceholderMap);
            $path = preg_quote($path, "\x01"); // \x01 is already encoded though
            $path = preg_replace_callback('/\x02(\w+)\x03/', function ($match) use ($metaPlaceholderMap) {
                return $metaPlaceholderMap[$match[1]];
            }, $path);
        }
        return $path;
    }

    protected function preparePathRuleRegEx(string $path, array &$metaPlaceholderMap = null)
    {
        $metaPlaceholderMap = [
            'w' => '.*?',
            'e' => '$',
        ];
        $path = preg_replace('/\*+/', "\x02w\x03", $path);
        $path = preg_replace('/(?:\x02w\x03)?\$+\z/', "\x02e\x03", $path);
        return $path;
    }

    protected function sortRules(array $rules)
    {
        // sort more descriptive first (aside from those include meta characters of which priority is undefined)
        uasort($rules, function ($a, $b) {
            return strlen($b['value']) <=> strlen($a['value']);
        });
        return $rules;
    }

    protected function compactRules(array $rules)
    {
        for (reset($rules); $index = key($rules), $rule = current($rules);) {
            if (($nextRule = next($rules)) === false) {
                break;
            }
            if ($nextRule['field'] !== $rule['field']
                    || strlen($nextRule['value']) + strlen($rule['value']) >= 4000) { // arbitrary limit
                continue;
            }
            $rules[$index]['value'] .= '|' . $nextRule['value'];
            unset($rules[key($rules)]);
        }
        return $rules;
    }
}
