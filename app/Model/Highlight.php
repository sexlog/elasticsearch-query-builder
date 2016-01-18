<?php

namespace sexlog\ElasticSearch\Model;

class Highlight
{
    /**
     * @var bool
     */
    private $highlight;

    public function __construct($highlight = true)
    {
        if (!is_bool($highlight)) {
            throw new \InvalidArgumentException;
        }

        $this->highlight = $highlight;
    }

    /**
     * @return boolean
     */
    public function shouldHighlight()
    {
        return $this->highlight;
    }

    /**
     * @param boolean $highlight
     */
    public function setHighlight($highlight)
    {
        $this->highlight = $highlight;
    }
}
