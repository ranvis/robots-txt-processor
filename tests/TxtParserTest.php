<?php
/**
 * @author SATO Kentaro
 * @license BSD 2-Clause License
 */

class TxtParserTest extends PHPUnit_Framework_TestCase
{
    public function testGetRuleIterator()
    {
        $source = "Name: value#comment\nAllow:***path \t#comment\n#comment\nDisallow:  /path \t\nDisallow: not-this-path\nNo;Colon\nName \t:\t va***lue\t ";
        $expectedList = [
            ['key' => "Name", 'value' => "value"],
            ['key' => "Allow", 'value' => "*path"],
            ['key' => "Disallow", 'value' => "/path \t"],
            ['key' => "Name", 'value' => "va***lue\t "],
        ];
        $parser = new \Ranvis\RobotsTxt\TxtParser();
        foreach ($parser->getRuleIterator($source) as $rule) {
            $expected = array_shift($expectedList);
            $this->assertSame($expected['key'], $rule['key']);
            $this->assertSame($expected['value'], $rule['value']);
        }
    }
}
