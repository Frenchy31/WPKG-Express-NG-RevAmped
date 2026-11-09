<?php

declare(strict_types=1);

namespace App\Controller;

use App\Import\WpkgImporter;
use Cake\Core\Configure;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Shows the settings and imports XML files.
 */
class AdminController extends AppController
{
    /**
     * Shows the page and processes uploaded files, packages first so profiles can reference them.
     *
     * @return void
     */
    public function index(): void
    {
        $reports = [];
        if ($this->request->is('post')) {
            $importer = new WpkgImporter();
            foreach (['packages', 'profiles', 'hosts'] as $kind) {
                $upload = $this->request->getData($kind);
                if (!$upload instanceof UploadedFileInterface || $upload->getError() !== UPLOAD_ERR_OK) {
                    continue;
                }
                $reports[$kind] = $importer->import($kind, (string)$upload->getStream());
            }
            if (!$reports) {
                $this->Flash->error(__('An error occurred while processing your input'));
            }
        }

        $this->set('reports', $reports);
        $this->set('settings', Configure::read('Wpkg'));
    }
}
