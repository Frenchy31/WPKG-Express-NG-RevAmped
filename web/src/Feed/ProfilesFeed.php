<?php

declare(strict_types=1);

namespace App\Feed;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use XMLWriter;

/**
 * Generates profiles.xml.
 */
class ProfilesFeed extends XmlFeed
{
    /**
     * @inheritDoc
     */
    protected function rootElement(): string
    {
        return 'profiles';
    }

    /**
     * @inheritDoc
     */
    protected function table(): Table
    {
        return $this->fetchTable('Profiles');
    }

    /**
     * @inheritDoc
     */
    protected function writeRecord(XMLWriter $writer, EntityInterface $record): void
    {
        $writer->startElement('profile');
        $writer->writeAttribute('id', $record->get('id_text'));
        $this->writeVariables($writer, $record);
        foreach ($record->get('profile_dependencies') as $dependency) {
            $writer->startElement('depends');
            $writer->writeAttribute('profile-id', $dependency->get('id_text'));
            $writer->endElement();
        }
        foreach ($record->get('packages') as $package) {
            $writer->startElement('package');
            $writer->writeAttribute('package-id', $package->get('id_text'));
            $writer->endElement();
        }
        $writer->endElement();
    }
}
