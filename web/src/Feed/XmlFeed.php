<?php

declare(strict_types=1);

namespace App\Feed;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use XMLWriter;

/**
 * Generates one of the XML documents read by wpkg.js.
 *
 * The output is contractual: a package or profile missing from a feed makes clients uninstall
 * software, and a changed revision makes them upgrade it.
 */
abstract class XmlFeed
{
    use LocatorAwareTrait;

    /**
     * @param bool $format Indent the output.
     * @param bool $exportDisabled Emit disabled records as XML comments.
     */
    public function __construct(
        protected readonly bool $format,
        protected readonly bool $exportDisabled,
    ) {
    }

    /**
     * Builds a feed from the application configuration.
     *
     * @return static
     */
    public static function fromConfiguration(): static
    {
        return new static(
            (bool)Configure::read('Wpkg.xml.format'),
            (bool)Configure::read('Wpkg.xml.exportDisabled'),
        );
    }

    /**
     * Renders the whole document.
     *
     * @return string
     */
    public function document(): string
    {
        $writer = $this->openWriter();
        $writer->startElement($this->rootElement());
        foreach ($this->records() as $record) {
            if ($record->get('enabled')) {
                $this->writeRecord($writer, $record);
            } else {
                $this->writeDisabledRecord($writer, $record);
            }
        }
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Renders a single record, or returns null when the feed does not expose it.
     *
     * @param int $recordId Record id.
     * @return string|null
     */
    public function record(int $recordId): ?string
    {
        $alias = $this->table()->getAlias();
        $record = $this->records()->where([$alias . '.id' => $recordId])->first();
        if ($record === null) {
            return null;
        }

        $writer = $this->openWriter();
        if ($record->get('enabled')) {
            $this->writeRecord($writer, $record);
        } else {
            $this->writeDisabledRecord($writer, $record);
        }
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Returns the name of the root element.
     *
     * @return string
     */
    abstract protected function rootElement(): string;

    /**
     * Returns the table holding the records.
     *
     * @return \Cake\ORM\Table
     */
    abstract protected function table(): Table;

    /**
     * Writes the element of one record.
     *
     * @param \XMLWriter $writer Writer.
     * @param \Cake\Datasource\EntityInterface $record Record.
     * @return void
     */
    abstract protected function writeRecord(XMLWriter $writer, EntityInterface $record): void;

    /**
     * Selects the records to render.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    protected function records(): SelectQuery
    {
        return $this->table()->find('forFeed', includeDisabled: $this->exportDisabled);
    }

    /**
     * Writes <variable> elements.
     *
     * @param \XMLWriter $writer Writer.
     * @param \Cake\Datasource\EntityInterface $record Variable owner.
     * @return void
     */
    protected function writeVariables(XMLWriter $writer, EntityInterface $record): void
    {
        foreach ($record->get('variables') as $variable) {
            $writer->startElement('variable');
            $writer->writeAttribute('name', (string)$variable->get('name'));
            $writer->writeAttribute('value', (string)$variable->get('value'));
            $writer->endElement();
        }
    }

    /**
     * Opens a writer on an in-memory document.
     *
     * @return \XMLWriter
     */
    private function openWriter(): XMLWriter
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        if ($this->format) {
            $writer->setIndent(true);
            $writer->setIndentString('    ');
        }
        $writer->startDocument('1.0', 'UTF-8');

        return $writer;
    }

    /**
     * Writes a disabled record as a comment. Clients ignore comments, they only help humans.
     *
     * @param \XMLWriter $writer Writer.
     * @param \Cake\Datasource\EntityInterface $record Record.
     * @return void
     */
    private function writeDisabledRecord(XMLWriter $writer, EntityInterface $record): void
    {
        $commentWriter = new XMLWriter();
        $commentWriter->openMemory();
        $this->writeRecord($commentWriter, $record);

        // A double hyphen is illegal inside an XML comment and would break the whole document.
        $writer->writeComment(' ' . str_replace('--', '- -', $commentWriter->outputMemory()) . ' ');
    }
}
