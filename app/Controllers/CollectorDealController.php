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

/**
 * Trilha de origem comercial da captação (Etapa 18C — Fase 2).
 *
 * Vincula o captador às entidades do funil (empresa, oportunidade, proposta,
 * patrocinador, financeiro) para responder "qual captação nasceu de qual
 * captador" — base da comissão na Fase 3.
 */
final class CollectorDealController extends Controller
{
    public function create(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        $collector = $this->findCollectorOr404($params['id'] ?? null);

        $this->renderForm($collector, [
            'deal_status'      => 'lead_indicado',
            'attribution_type' => 'direta',
        ], [], false);
    }

    public function store(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        csrf_verify();
        $collector = $this->findCollectorOr404($params['id'] ?? null);

        $model = new CollectorDeal();
        $data = $this->collectInput();
        $data['collector_id'] = (int) $collector['id'];
        $data['created_by'] = $_SESSION['user_id'] ?? null;

        $errors = $model->validate($data);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($collector, $data, $errors, false);
            return;
        }

        $id = (int) $model->create($data);
        (new ActivityLog())->record('collector_deal_created', $_SESSION['user_id'] ?? null, 'collector_deal', $id);
        flash('success', 'Captação rastreada.');
        $this->redirect('/collectors/' . (int) $collector['id']);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        $model = new CollectorDeal();
        $deal = $model->findById((int) ($params['id'] ?? 0));
        if ($deal === null) {
            $this->abort(404, 'Captação não encontrada.');
        }
        $collector = $this->findCollectorOr404($deal['collector_id']);

        $this->renderForm($collector, $deal, [], true);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        csrf_verify();
        $model = new CollectorDeal();
        $deal = $model->findById((int) ($params['id'] ?? 0));
        if ($deal === null) {
            $this->abort(404, 'Captação não encontrada.');
        }
        $collector = $this->findCollectorOr404($deal['collector_id']);

        $data = $this->collectInput();
        $data['collector_id'] = (int) $collector['id'];
        $data['updated_by'] = $_SESSION['user_id'] ?? null;

        $errors = $model->validate($data);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($collector, array_merge($deal, $data), $errors, true);
            return;
        }

        $model->update((int) $deal['id'], $data);
        (new ActivityLog())->record('collector_deal_updated', $_SESSION['user_id'] ?? null, 'collector_deal', (int) $deal['id']);
        flash('success', 'Captação atualizada.');
        $this->redirect('/collectors/' . (int) $collector['id']);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        csrf_verify();
        $model = new CollectorDeal();
        $deal = $model->findById((int) ($params['id'] ?? 0));
        if ($deal === null) {
            $this->abort(404, 'Captação não encontrada.');
        }

        $model->archive((int) $deal['id']);
        (new ActivityLog())->record('collector_deal_archived', $_SESSION['user_id'] ?? null, 'collector_deal', (int) $deal['id']);
        flash('success', 'Captação arquivada.');
        $this->redirect('/collectors/' . (int) $deal['collector_id']);
    }

    /**
     * @param array<string, mixed> $collector
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderForm(array $collector, array $data, array $errors, bool $isEdit): void
    {
        $model = new CollectorDeal();
        $this->view('collector_deals/form', [
            'title'            => $isEdit ? 'Editar captação' : 'Nova captação rastreada',
            'collector'        => $collector,
            'data'             => $data,
            'errors'           => $errors,
            'isEdit'           => $isEdit,
            'companies'        => (new Company())->activeOptions(),
            'opportunities'    => $model->opportunityOptions(),
            'proposals'        => $model->proposalOptions(),
            'sponsors'         => $model->sponsorOptions(),
            'statuses'         => $model->getStatuses(),
            'attributionTypes' => $model->getAttributionTypes(),
        ]);
    }

    /** @return array<string, mixed> */
    private function collectInput(): array
    {
        $nullableId = static fn (string $key): ?int => (int) input($key, 0) > 0 ? (int) input($key, 0) : null;

        return [
            'company_id'              => (int) input('company_id', 0),
            'contact_id'              => $nullableId('contact_id'),
            'opportunity_id'          => $nullableId('opportunity_id'),
            'proposal_id'             => $nullableId('proposal_id'),
            'sponsor_id'              => $nullableId('sponsor_id'),
            'financial_entry_id'      => $nullableId('financial_entry_id'),
            'collector_assignment_id' => $nullableId('collector_assignment_id'),
            'deal_status'             => trim((string) input('deal_status', 'lead_indicado')),
            'attribution_type'        => trim((string) input('attribution_type', 'direta')),
            'source'                  => trim((string) input('source', '')),
            'notes'                   => trim((string) input('notes', '')),
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
