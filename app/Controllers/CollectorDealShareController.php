<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Collector;
use App\Models\CollectorDeal;
use App\Models\CollectorDealShare;

/**
 * Rateio de comissao para captacao compartilhada (Etapa 20B-3).
 */
final class CollectorDealShareController extends Controller
{
    public function index(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');

        $deal = $this->dealOr404((int) ($params['id'] ?? 0));
        $shareModel = new CollectorDealShare();

        $this->view('collector_deal_shares/index', [
            'title' => 'Rateio da Captacao #' . (int) $deal['id'],
            'deal' => $deal,
            'shares' => $shareModel->findByDeal((int) $deal['id']),
            'statuses' => $shareModel->getStatuses(),
            'collectors' => $this->collectorOptions(),
        ]);
    }

    public function store(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        csrf_verify();

        $deal = $this->dealOr404((int) ($params['id'] ?? 0));
        try {
            $id = (new CollectorDealShare())->createForDeal($deal, [
                'collector_id' => input('collector_id', 0),
                'share_percent' => input('share_percent', ''),
                'notes' => input('notes', ''),
            ], $_SESSION['user_id'] ?? null);
            (new ActivityLog())->record('collector_deal_share_created', $_SESSION['user_id'] ?? null, 'collector_deal_share', $id);
            flash('success', 'Rateio cadastrado.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/collector-deals/' . (int) $deal['id'] . '/shares');
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        csrf_verify();

        $shareId = (int) ($params['id'] ?? 0);
        $dealId = (int) input('collector_deal_id', 0);
        try {
            (new CollectorDealShare())->updateShare($shareId, [
                'share_percent' => input('share_percent', ''),
                'notes' => input('notes', ''),
            ], $_SESSION['user_id'] ?? null);
            (new ActivityLog())->record('collector_deal_share_updated', $_SESSION['user_id'] ?? null, 'collector_deal_share', $shareId);
            flash('success', 'Rateio atualizado.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/collector-deals/' . $dealId . '/shares');
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        csrf_verify();

        $shareId = (int) ($params['id'] ?? 0);
        $dealId = (int) input('collector_deal_id', 0);
        try {
            (new CollectorDealShare())->archiveShare($shareId, $_SESSION['user_id'] ?? null);
            (new ActivityLog())->record('collector_deal_share_archived', $_SESSION['user_id'] ?? null, 'collector_deal_share', $shareId);
            flash('success', 'Rateio arquivado.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/collector-deals/' . $dealId . '/shares');
    }

    public function approve(array $params): void
    {
        AuthMiddleware::requirePermission('collector_deals.manage');
        csrf_verify();

        $deal = $this->dealOr404((int) ($params['id'] ?? 0));
        try {
            (new CollectorDealShare())->approveDealShares((int) $deal['id'], $_SESSION['user_id'] ?? null);
            (new ActivityLog())->record('collector_deal_share_approved', $_SESSION['user_id'] ?? null, 'collector_deal', (int) $deal['id']);
            flash('success', 'Rateio aprovado.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/collector-deals/' . (int) $deal['id'] . '/shares');
    }

    /** @return array<string, mixed> */
    private function dealOr404(int $id): array
    {
        $deal = (new CollectorDeal())->findById($id);
        if ($deal === null) {
            $this->abort(404, 'Captacao nao encontrada.');
        }

        return $deal;
    }

    /** @return array<int, array{id:int,label:string}> */
    private function collectorOptions(): array
    {
        $items = (new Collector())->paginate(['show_archived' => 0], 1, 500);
        return array_map(static fn (array $c): array => [
            'id' => (int) $c['id'],
            'label' => trim((string) ($c['name'] ?? '')) . (!empty($c['collector_code']) ? ' - ' . (string) $c['collector_code'] : ''),
        ], $items);
    }
}
