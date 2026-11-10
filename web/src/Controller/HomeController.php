<?php

declare(strict_types=1);

namespace App\Controller;

use App\Report\HostMatcher;
use App\Report\IndexState;
use App\Report\ReportDirectory;
use App\Report\ReportSummary;
use Cake\Core\Configure;
use Cake\ORM\Table;
use DateTimeImmutable;

/**
 * Dashboard and global search.
 */
class HomeController extends AppController
{
    /**
     * Searchable columns of each table, the display column first.
     */
    private const array SEARCH_FIELDS = [
        'Packages' => ['name', 'id_text', 'notes'],
        'Profiles' => ['id_text', 'notes'],
        'Hosts' => ['name', 'notes'],
    ];

    /**
     * Shows the state of the fleet from the reports index, then counters and recent changes of the configuration.
     *
     * @return void
     */
    public function index(): void
    {
        $isConfigured = ReportDirectory::fromConfiguration()->isConfigured();
        $reports = $isConfigured ? $this->fetchTable('HostReports')->allReports() : [];
        $indexState = $this->fetchTable('ReportIndexState')->latest();
        $intervalSeconds = (int)Configure::read('Wpkg.reportsIndexInterval');
        $now = new DateTimeImmutable();
        $this->set([
            'configured' => $isConfigured,
            'groups' => (new HostMatcher())->groupForDashboard($reports),
            'summary' => new ReportSummary($reports, $now),
            'indexState' => $indexState,
            'indexOverdue' => IndexState::isOverdue($indexState, $now, $intervalSeconds),
            'now' => $now,
        ]);
        $this->set('statistics', [
            'Packages' => $this->statistics($this->fetchTable('Packages'), 'name'),
            'Profiles' => $this->statistics($this->fetchTable('Profiles'), 'id_text'),
            'Hosts' => $this->statistics($this->fetchTable('Hosts'), 'name'),
        ]);
    }

    /**
     * Searches names, identifiers and notes.
     *
     * @return void
     */
    public function search(): void
    {
        $searchText = trim((string)$this->request->getQuery('q'));
        $requestedType = ucfirst((string)$this->request->getQuery('type', 'all'));
        $tableNames = isset(self::SEARCH_FIELDS[$requestedType]) ? [$requestedType] : array_keys(self::SEARCH_FIELDS);

        $results = [];
        if ($searchText !== '') {
            foreach ($tableNames as $tableName) {
                $conditions = [];
                foreach (self::SEARCH_FIELDS[$tableName] as $field) {
                    $conditions[$tableName . '.' . $field . ' LIKE'] = '%' . $searchText . '%';
                }
                $results[$tableName] = $this->fetchTable($tableName)->find()
                    ->where(['OR' => $conditions])
                    ->orderBy([$tableName . '.' . self::SEARCH_FIELDS[$tableName][0] => 'ASC'])
                    ->all()
                    ->toList();
            }
        }

        $this->set('searchFields', self::SEARCH_FIELDS);
        $this->set(compact('searchText', 'results'));
    }

    /**
     * Computes the dashboard block of a table.
     *
     * @param \Cake\ORM\Table $table Table.
     * @param string $displayField Column shown in the recent list.
     * @return array<string, mixed>
     */
    private function statistics(Table $table, string $displayField): array
    {
        $total = $table->find()->count();
        $enabled = $table->find()->where(['enabled' => true])->count();

        return [
            'enabled' => $enabled,
            'disabled' => $total - $enabled,
            'total' => $total,
            'displayField' => $displayField,
            'recent' => $table->find()->orderBy(['modified' => 'DESC'])->limit(5)->all()->toList(),
        ];
    }
}
