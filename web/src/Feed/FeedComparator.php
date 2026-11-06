<?php

declare(strict_types=1);

namespace App\Feed;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Compares a feed captured from the legacy application with the current output, as clients read them.
 *
 * Quoting, attribute order and indentation do not matter to clients; element order and attribute
 * values do. The differences accepted on purpose are applied in `normalizeLegacy()`.
 */
class FeedComparator
{
    /**
     * Lists the differences between two documents.
     *
     * @param string $legacyXml Document served by the legacy application.
     * @param string $currentXml Document served by this application.
     * @return array<string> One line per difference, empty when clients see the same thing.
     */
    public function differences(string $legacyXml, string $currentXml): array
    {
        $legacy = $this->elements($this->normalizeLegacy($legacyXml));
        $current = $this->elements($currentXml);
        if ($legacy === $current) {
            return [];
        }

        $differences = [];
        foreach (array_diff($legacy, $current) as $line) {
            $differences[] = '- ' . trim($line);
        }
        foreach (array_diff($current, $legacy) as $line) {
            $differences[] = '+ ' . trim($line);
        }

        return $differences ?: ['Same elements, in a different order.'];
    }

    /**
     * Counts the elements of a document, comments excluded.
     *
     * @param string $xml Document.
     * @return int
     */
    public function elementCount(string $xml): int
    {
        return count($this->elements($this->normalizeLegacy($xml)));
    }

    /**
     * Makes a legacy document parsable: the application decoded entities after serializing,
     * so a bare `&` broke the document, and commented-out records may contain `--`.
     *
     * @param string $xml Legacy document.
     * @return string
     */
    private function normalizeLegacy(string $xml): string
    {
        $withoutComments = (string)preg_replace('/<!--.*?-->/s', '', $xml);

        return (string)preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;)/', '&amp;', $withoutComments);
    }

    /**
     * Flattens a document into one line per element, in document order.
     *
     * @param string $xml Document.
     * @return array<string>
     * @throws \RuntimeException When the document is not well-formed.
     */
    private function elements(string $xml): array
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        if (!@$document->loadXML($xml, LIBXML_NONET) || $document->documentElement === null) {
            throw new RuntimeException('The document is not well-formed.');
        }

        $lines = [];
        $this->appendElement($document->documentElement, 0, $lines);

        return $lines;
    }

    /**
     * Appends an element and its descendants.
     *
     * @param \DOMElement $element Element.
     * @param int $depth Nesting depth.
     * @param array<string> $lines Result.
     * @return void
     */
    private function appendElement(DOMElement $element, int $depth, array &$lines): void
    {
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }
        // Legacy dropped the count of atleast/atmost checks, which then made wpkg.js throw.
        if ($element->localName === 'check' && ($attributes['type'] ?? '') === 'logical') {
            unset($attributes['value']);
        }
        ksort($attributes);

        $line = str_repeat(' ', $depth) . $element->localName;
        foreach ($attributes as $name => $attributeValue) {
            $line .= sprintf(' %s="%s"', $name, $attributeValue);
        }
        $lines[] = $line;

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $this->appendElement($child, $depth + 1, $lines);
            }
        }
    }
}
