<?php

namespace GFtoXML\ArrayToXml;

use DOMElement;
use DOMDocument;
use DOMException;

class ArrayToXml
{
    /**
     * The root DOM Document.
     *
     * @var \DOMDocument
     */
    protected $document;

    /**
     * Set to enable replacing space with underscore.
     *
     * @var bool
     */
    protected $replaceSpacesByUnderScoresInKeyNames = true;

    /**
     * Construct a new instance.
     *
     * @param string[] $array
     * @param string   $rootElementName
     * @param bool     $replaceSpacesByUnderScoresInKeyNames
     *
     * @throws DOMException
     */
    public function __construct(array $array, $rootElementName = '', $replaceSpacesByUnderScoresInKeyNames = true)
    {
        $this->document = new DOMDocument();
        $this->document->formatOutput = true;
        $this->replaceSpacesByUnderScoresInKeyNames = $replaceSpacesByUnderScoresInKeyNames;

        if ($this->isArrayAllKeySequential($array) && ! empty($array)) {
            throw new DOMException('Invalid Character Error');
        }

        $root = $this->document->createElement($rootElementName == '' ? 'root' : $rootElementName);

        $this->document->appendChild($root);

        $this->convertElement($root, $array);
    }

    /**
     * Convert the given array to an xml string.
     *
     * @param string[] $array
     * @param string   $rootElementName
     * @param bool     $replaceSpacesByUnderScoresInKeyNames
     *
     * @return string
     */
    public static function convert(array $array, $rootElementName = '', $replaceSpacesByUnderScoresInKeyNames = true)
    {
        $converter = new static($array, $rootElementName, $replaceSpacesByUnderScoresInKeyNames);

        return $converter->toXml();
    }

    /**
     * Return as XML.
     *
     * @return string
     */
    public function toXml()
    {
        return $this->document->saveXML();
    }

    /**
     * Return as DOM object.
     *
     * @return DOMDocument
     */
    public function toDom()
    {
        return $this->document;
    }

    /**
     * Parse individual element.
     *
     * @param \DOMElement     $element
     * @param string|string[] $value
     */
    private function convertElement(DOMElement $element, $value)
    {
        $sequential = $this->isArrayAllKeySequential($value);

        if (! is_array($value)) {
            $element->nodeValue = htmlspecialchars($value);

            return;
        }

        foreach ($value as $key => $data) {
            if (! $sequential) {
                if (($key === '_attributes') || ($key === '@attributes')) {
                    $this->addAttributes($element, $data);
                } elseif ((($key === '_value') || ($key === '@value')) && is_string($data)) {
                    $element->nodeValue = htmlspecialchars($data);
                } else {
                    $this->addNode($element, $key, $data);
                }
            } elseif (is_array($data)) {
                $this->addCollectionNode($element, $data);
            } else {
                $this->addSequentialNode($element, $data);
            }
        }
    }

    /**
     * Add node.
     *
     * @param \DOMElement     $element
     * @param string          $key
     * @param string|string[] $value
     */
    protected function addNode(DOMElement $element, $key, $value)
    {
        if ($this->replaceSpacesByUnderScoresInKeyNames) {
            $key = str_replace(' ', '_', $key);
        }

        /* Normalize $key into a valid XML element name */

        // strips any non alphabetical character in the first position
        $key = preg_replace('/^[^a-zA-Z]{1}/', '', $key);

        // using regex to check for xml is overkill
        $key = (strpos(strtolower($key), 'xml') === 0) ? substr($key, 3) : $key;

        // strips any whitespace
        $key = str_replace(' ', '', $key);

        // remove invalid characters for XML element names
        $search = [':','?',',',';','/','\\','[',']'];
        $key = str_replace( $search, '', $key );
        $key = strtolower( $key );

        // convert double underscores to single underscore
        $search = ['__'];
        $key = str_replace( $search, '_', $key );

        $child = $this->document->createElement( $key ) ;
        $element->appendChild($child);
        $this->convertElement($child, $value);
    }

    /**
     * Add collection node.
     *
     * @param \DOMElement     $element
     * @param string|string[] $value
     *
     * @internal param string $key
     */
    protected function addCollectionNode(DOMElement $element, $value)
    {
        if ($element->childNodes->length == 0) {
            $this->convertElement($element, $value);

            return;
        }

        $child = $element->cloneNode();
        $element->parentNode->appendChild($child);
        $this->convertElement($child, $value);
    }

    /**
     * Add sequential node.
     *
     * @param \DOMElement     $element
     * @param string|string[] $value
     *
     * @internal param string $key
     */
    protected function addSequentialNode(DOMElement $element, $value)
    {
        if (empty($element->nodeValue)) {
            $element->nodeValue = htmlspecialchars($value);

            return;
        }

        $child = $element->cloneNode();
        $child->nodeValue = htmlspecialchars($value);
        $element->parentNode->appendChild($child);
    }

    /**
     * Check if array are all sequential.
     *
     * @param array|string $value
     *
     * @return bool
     */
    protected function isArrayAllKeySequential($value)
    {
        if (! is_array($value)) {
            return false;
        }

        if (count($value) <= 0) {
            return true;
        }

        return array_unique(array_map('is_int', array_keys($value))) === [true];
    }

    /**
     * Add attributes.
     *
     * @param \DOMElement $element
     * @param string[]    $data
     */
    protected function addAttributes($element, $data)
    {
        foreach ($data as $attrKey => $attrVal) {
            $element->setAttribute($attrKey, $attrVal);
        }
    }
}