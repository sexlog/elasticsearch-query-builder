<?php

use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    /**
     * @var \sexlog\ElasticSearch\Filter
     */
    private $filter;

    protected function setUp(): void
    {
        $this->filter = new \sexlog\ElasticSearch\Filter();
    }

    public function testNotWhere()
    {
        $this->markTestIncomplete('This test hasn\'t been implemented yet');
    }

    public function testNotWhereIn()
    {
        $this->markTestIncomplete('This test hasn\'t been implemented yet');
    }

    public function testExists()
    {
        $this->markTestIncomplete('This test hasn\'t been implemented yet');
    }

    public function testNotExists()
    {
        $this->markTestIncomplete('This test hasn\'t been implemented yet');
    }

    public function testLocation()
    {
        $this->markTestIncomplete('This test hasn\'t been implemented yet');
    }
}
