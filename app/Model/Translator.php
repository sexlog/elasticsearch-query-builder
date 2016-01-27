<?php
namespace sexlog\ElasticSearch\Model;

use sexlog\ElasticSearch\Exceptions\FileNotFoundException;
use sexlog\ElasticSearch\Exceptions\InvalidLocaleException;
use sexlog\ElasticSearch\Exceptions\MissingLangException;

class Translator
{
    /**
     * @var array
     */
    private $availableLocales = ['en-us', 'pt-br'];

    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $file;

    /**
     * @var array
     */
    private $langs;

    public function __construct($locale = 'en-us')
    {
        $this->setLocale($locale);

        $this->addResource();
    }

    /**
     * @param mixed $langs
     */
    private function setLangs($langs)
    {
        if (!is_array($langs)) {
            throw new \InvalidArgumentException;
        }

        $this->langs = $langs;
    }

    /**
     * @param $locale
     *
     * @throws FileNotFoundException
     */
    private function setLocale($locale)
    {
        if (!$this->isLocale($locale)) {
            throw new InvalidLocaleException;
        }

        if (!$this->localeExists($locale)) {
            throw new FileNotFoundException;
        }

        $this->locale = $locale;
    }

    /**
     * @throws FileNotFoundException
     */
    private function addResource()
    {
        $langs = require($this->file);

        $this->setLangs($langs);
    }

    /**
     * @param $locale
     *
     * @return bool
     */
    private function isLocale($locale)
    {
        $locale = strtolower($locale);

        if (!in_array($locale, $this->availableLocales)) {
            return false;
        }

        return true;
    }

    /**
     * @param $locale
     *
     * @return bool
     */
    private function localeExists($locale)
    {
        $langFile = __DIR__ . '/../Lang/' . $locale . '.php';

        if (!file_exists($langFile)) {
            return false;
        }

        $this->file = $langFile;

        return true;
    }

    /**
     * @param $key
     *
     * @return mixed
     * @throws MissingLangException
     */
    public function get($key)
    {
        if(!isset($this->langs[$key])) {
            throw new MissingLangException;
        }

        return $this->langs[$key];
    }
}
