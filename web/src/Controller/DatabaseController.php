<?php

declare(strict_types=1);

namespace App\Controller;

use App\Backup\BackupException;
use App\Backup\BackupRepository;
use App\Backup\DatabaseBackup;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Exports, imports and empties the business tables from the Admin page. Every action needs a POST request.
 */
class DatabaseController extends AppController
{
    /**
     * Sends the export as an SQL file named after the current time.
     *
     * @return \Cake\Http\Response
     */
    public function export(): Response
    {
        $this->request->allowMethod('post');

        $now = DateTime::now();
        $file = $this->backup()->export($now);

        return $this->response
            ->withHeader('Content-Type', 'application/sql; charset=UTF-8')
            ->withHeader('Content-Length', (string)$file->getSize())
            ->withDownload('wpkg-express-ng-' . $now->format('Ymd-His') . '.sql')
            ->withDisabledCache()
            ->withBody($file);
    }

    /**
     * Replaces the content of the database with an uploaded export.
     *
     * @return \Cake\Http\Response
     */
    public function import(): Response
    {
        $this->request->allowMethod('post');

        $upload = $this->request->getData('backup');
        if (!$upload instanceof UploadedFileInterface || $upload->getError() !== UPLOAD_ERR_OK) {
            $this->Flash->error($this->uploadErrorMessage($upload));

            return $this->redirect(['controller' => 'Admin', 'action' => 'index']);
        }

        try {
            $rowCount = $this->backup()->import($upload->getStream()->detach());
            $this->Flash->success(
                __('The database was replaced with the content of the file: {0} rows imported.', $rowCount),
            );
        } catch (BackupException $exception) {
            $this->Flash->error($exception->getMessage());
        }

        return $this->redirect(['controller' => 'Admin', 'action' => 'index']);
    }

    /**
     * Deletes every host, profile, package and what belongs to them.
     *
     * @return \Cake\Http\Response
     */
    public function reset(): Response
    {
        $this->request->allowMethod('post');

        try {
            $this->backup()->reset();
            $this->Flash->success(__('The database was emptied.'));
        } catch (BackupException $exception) {
            $this->Flash->error($exception->getMessage());
        }

        return $this->redirect(['controller' => 'Admin', 'action' => 'index']);
    }

    /**
     * Builds the service on the application database.
     *
     * @return \App\Backup\DatabaseBackup
     */
    private function backup(): DatabaseBackup
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('default');

        return new DatabaseBackup(new BackupRepository($connection));
    }

    /**
     * Explains why no file reached the import.
     *
     * @param mixed $upload Value of the `backup` field.
     * @return string
     */
    private function uploadErrorMessage(mixed $upload): string
    {
        $error = $upload instanceof UploadedFileInterface ? $upload->getError() : UPLOAD_ERR_NO_FILE;

        return match ($error) {
            UPLOAD_ERR_NO_FILE => __('Select the file to import.'),
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __(
                'The file is larger than the upload limit of {0}.',
                (string)ini_get('upload_max_filesize'),
            ),
            default => __('The file could not be uploaded.'),
        };
    }
}
