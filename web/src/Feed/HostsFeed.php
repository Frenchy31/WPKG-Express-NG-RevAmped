<?php

declare(strict_types=1);

namespace App\Feed;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use XMLWriter;

/**
 * Generates hosts.xml.
 */
class HostsFeed extends XmlFeed
{
    /**
     * @inheritDoc
     */
    protected function rootElement(): string
    {
        return 'wpkg';
    }

    /**
     * @inheritDoc
     */
    protected function table(): Table
    {
        return $this->fetchTable('Hosts');
    }

    /**
     * @inheritDoc
     */
    protected function writeRecord(XMLWriter $writer, EntityInterface $record): void
    {
        $writer->startElement('host');
        $writer->writeAttribute('name', $record->get('name'));
        $writer->writeAttribute('profile-id', (string)$record->get('main_profile')?->get('id_text'));
        $this->writeVariables($writer, $record);
        foreach ($record->get('profiles') as $profile) {
            $writer->startElement('profile');
            $writer->writeAttribute('id', $profile->get('id_text'));
            $writer->endElement();
        }
        $writer->endElement();
    }
}
