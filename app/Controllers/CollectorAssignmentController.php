<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Collector;
use App\Models\CollectorAssignment;
use App\Models\CollectorDeal;
use App\Models\Company;
use App\Models\IncentiveProject;
use App\Models\Opportunity;

/**
 * Atribuição comercial de empresas a captadores (Etapa 18C — Fase 2).
 *
 * Autoriza/reserva a abordagem de uma empresa por um captador antes do
 * trabalho comercial, evitando conflito entre captadores e dando base à
 * rastreabilidade (collector_deals).
 */
final class CollectorAssignmentController extends Controller
{
    public function create(array $params): void
    {
        AuthMiddleware::requirePermission('collector_assignments.manage');
        $collector = $this->findCollectorOr404($params['id'] ?? null);

        $this->renderForm($collector, [
            'assignment_type' => 'exclusiva',
            'status'          => 'solicitada',
        ], []);
    }

    public function store(array $params): void
    {
        AuthMiddleware::requirePermission('collector_assignments.manage');
        csrf_verify();
        $collector = $this->findCollectorOr404($params['id'] ?? null);

        $model = new CollectorAssignment();
        $data = $this->collectInput();
        $data['collector_id'] = (int) $collector['id'];
        $data['created_by'] = $_SESSION['user_id'] ?? null;

        $errors = $model->validate($data);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($collector, $data, $errors);
            return;
        }

        $conflict = $model->findExclusiveConflict(
            (int) $data['company_id'],
            (string) $data['assignment_type'],
            $data['exclusive_until'] ?? null,
            null,
            $data['incentive_project_id'] ?? null
        );
        if ($conflict !== null) {
            $errors['company_id'] = 'Já existe atribuição exclusiva ativa desta empresa para o captador "'
                . (string) ($conflict['collector_name'] ?? 'outro') . '". Cancele-a ou use atribuição não exclusiva.';
            http_response_code(409);
            $this->renderForm($collector, $data, $errors);
            return;
        }

        $id = (int) $model->create($data);
        (new ActivityLog())->record('collector_assignment_created', $_SESSION['user_id'] ?? null, 'collector_assignment', $id);
        flash('success', 'Atribuição registrada.');
        $this->redirect('/collectors/' . (int) $collector['id']);
    }

    public function authorize(array $params): void
    {
        AuthMiddleware::requirePermission('collector_assignments.manage');
        csrf_verify();
        $model = new CollectorAssignment();
        $assignment = $model->findById((int) ($params['id'] ?? 0));
        if ($assignment === null) {
            $this->abort(404, 'Atribuição não encontrada.');
        }

        // Re-checa conflito de exclusividade no momento da autorização.
        $conflict = $model->findExclusiveConflict(
            (int) $assignment['company_id'],
            (string) $assignment['assignment_type'],
            $assignment['exclusive_until'] ?? null,
            (int) $assignment['id'],
            $assignment['incentive_project_id'] ?? null
        );
        if ($conflict !== null) {
            flash('error', 'Não é possível autorizar: já existe atribuição exclusiva ativa desta empresa para outro captador.');
            $this->redirect('/collectors/' . (int) $assignment['collector_id']);
            return;
        }

        $model->authorize((int) $assignment['id'], $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('collector_assignment_authorized', $_SESSION['user_id'] ?? null, 'collector_assignment', (int) $assignment['id']);
        flash('success', 'Abordagem autorizada.');
        $this->redirect('/collectors/' . (int) $assignment['collector_id']);
    }

    public function cancel(array $params): void
    {
        AuthMiddleware::requirePermission('collector_assignments.manage');
        csrf_verify();
        $model = new CollectorAssignment();
        $assignment = $model->findById((int) ($params['id'] ?? 0));
        if ($assignment === null) {
            $this->abort(404, 'Atribuição não encontrada.');
        }

        $model->cancel((int) $assignment['id'], $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('collector_assignment_cancelled', $_SESSION['user_id'] ?? null, 'collector_assignment', (int) $assignment['id']);
        flash('success', 'Atribuição cancelada.');
        $this->redirect('/collectors/' . (int) $assignment['collector_id']);
    }

    /**
     * Converte uma autorização em oportunidade no funil e abre a trilha (deal).
     */
    public function convert(array $params): void
    {
        AuthMiddleware::requirePermission('collector_assignments.manage');
        csrf_verify();
        $model = new CollectorAssignment();
        $assignment = $model->findById((int) ($params['id'] ?? 0));
        if ($assignment === null) {
            $this->abort(404, 'Atribuição não encontrada.');
        }

        if ((string) $assignment['status'] !== 'autorizada') {
            flash('error', 'Só é possível converter uma atribuição autorizada.');
            $this->redirect('/collectors/' . (int) $assignment['collector_id']);
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $companyName = (string) ($assignment['company_name'] ?? 'Empresa');
        $projectId = isset($assignment['incentive_project_id']) && $assignment['incentive_project_id'] !== null
            ? (int) $assignment['incentive_project_id']
            : null;
        if ($projectId === null || $projectId <= 0) {
            flash('error', 'Nao e possivel converter uma atribuicao sem projeto incentivado.');
            $this->redirect('/collectors/' . (int) $assignment['collector_id']);
            return;
        }

        $opportunityId = (int) (new Opportunity())->create([
            'incentive_project_id' => $projectId,
            'company_id'  => (int) $assignment['company_id'],
            'title'       => 'Captação — ' . $companyName,
            'status'      => 'prospect_identificado',
            'probability' => 5,
            'source'      => 'captador',
            'owner_user_id' => $userId,
            'opened_at'   => date('Y-m-d H:i:s'),
            'created_by'  => $userId,
        ]);

        $dealId = (int) (new CollectorDeal())->create([
            'incentive_project_id'    => $projectId,
            'collector_id'            => (int) $assignment['collector_id'],
            'collector_assignment_id' => (int) $assignment['id'],
            'company_id'              => (int) $assignment['company_id'],
            'opportunity_id'          => $opportunityId,
            'deal_status'             => 'oportunidade_criada',
            'attribution_type'        => $assignment['assignment_type'] === 'nao_exclusiva' ? 'compartilhada' : 'direta',
            'source'                  => 'conversao_atribuicao',
            'created_by'              => $userId,
        ]);

        $model->markConverted((int) $assignment['id'], $userId);
        $log = new ActivityLog();
        $log->record('collector_assignment_converted', $userId, 'collector_assignment', (int) $assignment['id']);
        $log->record('collector_deal_created', $userId, 'collector_deal', $dealId);

        flash('success', 'Oportunidade criada e captação rastreada.');
        $this->redirect('/opportunities/' . $opportunityId);
    }

    /**
     * @param array<string, mixed> $collector
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderForm(array $collector, array $data, array $errors): void
    {
        $model = new CollectorAssignment();
        $this->view('collector_assignments/form', [
            'title'      => 'Nova atribuição comercial',
            'collector'  => $collector,
            'data'       => $data,
            'errors'     => $errors,
            'companies'  => (new Company())->activeOptions(),
            'projects'   => (new IncentiveProject())->options(true),
            'types'      => $model->getTypes(),
        ]);
    }

    /** @return array<string, mixed> */
    private function collectInput(): array
    {
        return [
            'incentive_project_id' => ($projectId = (int) input('incentive_project_id', 0)) > 0 ? $projectId : null,
            'company_id'      => (int) input('company_id', 0),
            'assignment_type' => trim((string) input('assignment_type', 'exclusiva')),
            'status'          => 'solicitada',
            'exclusive_until' => trim((string) input('exclusive_until', '')),
            'notes'           => trim((string) input('notes', '')),
        ];
    }

    /** @return array<string, mixed> */
    private function findCollectorOr404(mixed $id): array
    {
        $row = (new Collector())->findById($id ?? 0);
        if ($row === null) {
            $this->abort(404, 'Captador não encontrado.');
        }

        return $row;
    }
}
