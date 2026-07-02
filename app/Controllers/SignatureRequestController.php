<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\CollectorApplication;
use App\Models\SignatureRequest;
use App\Services\EmailEventService;

final class SignatureRequestController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('signature_requests.view');

        $model   = new SignatureRequest();
        $filters = [
            'status'       => trim((string) input('status', '')),
            'source_type'  => trim((string) input('source_type', '')),
            'show_archived'=> input('show_archived') === '1',
        ];
        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('signature_requests/index', [
            'title'    => 'Assinaturas',
            'items'    => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'  => $filters,
            'statuses' => $model->getStatuses(),
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
        ]);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('signature_requests.view');
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];
        $model = new SignatureRequest();
        $signers = $model->signersForRequest($id);

        $signLinks = [];
        foreach ($signers as $signer) {
            if (!empty($signer['public_token'])) {
                $signLinks[(int) $signer['id']] = app_url('/assinatura/' . rawurlencode((string) $signer['public_token']));
            }
        }

        $this->view('signature_requests/show', [
            'title'     => $item['title'] ?? 'Assinatura',
            'item'      => $item,
            'signers'   => $signers,
            'signLinks' => $signLinks,
            'statuses'  => $model->getStatuses(),
        ]);
    }

    public function send(array $params): void
    {
        AuthMiddleware::requirePermission('signature_requests.send');
        csrf_verify();
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];

        if ((string) ($item['status'] ?? '') === 'assinado') {
            flash('warning', 'Documento já assinado.');
            $this->redirect('/signature-requests/' . $id);
            return;
        }

        (new SignatureRequest())->send($id, $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('signature_request_sent', $_SESSION['user_id'] ?? null, 'signature_request', $id);
        $updated = (new SignatureRequest())->findById($id) ?? $item;
        if ((string) ($updated['source_type'] ?? '') === 'collector_application' && (int) ($updated['source_id'] ?? 0) > 0) {
            $applicationModel = new CollectorApplication();
            $applicationId = (int) $updated['source_id'];
            $application = $applicationModel->findById($applicationId);
            if ($application !== null) {
                $token = trim((string) ($application['public_token'] ?? ''));
                if ($token === '' || !$applicationModel->validatePublicToken($application)['valid']) {
                    $token = $applicationModel->generatePublicToken($applicationId, 30);
                    $application = $applicationModel->findById($applicationId) ?? array_merge($application, [
                        'public_token' => $token,
                    ]);
                }
                $publicUrl = app_url('/captadores/credenciamento/' . rawurlencode($token));
                (new EmailEventService())->sendToCollector('signature_request_sent', $application, [
                    'public_url' => $publicUrl,
                    'signature_url' => $publicUrl,
                ]);
            }
        }
        flash('success', 'Processo de assinatura enviado.');
        $this->redirect('/signature-requests/' . $id);
    }

    public function cancel(array $params): void
    {
        AuthMiddleware::requirePermission('signature_requests.cancel');
        csrf_verify();
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];

        if ((string) ($item['status'] ?? '') === 'assinado') {
            flash('error', 'Não é possível cancelar documento já assinado.');
            $this->redirect('/signature-requests/' . $id);
            return;
        }

        (new SignatureRequest())->cancel($id, $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('signature_request_cancelled', $_SESSION['user_id'] ?? null, 'signature_request', $id);
        flash('success', 'Assinatura cancelada.');
        $this->redirect('/signature-requests/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('signature_requests.archive');
        csrf_verify();
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];
        (new SignatureRequest())->archive($id);
        (new ActivityLog())->record('signature_request_archived', $_SESSION['user_id'] ?? null, 'signature_request', $id);
        flash('success', 'Processo arquivado.');
        $this->redirect('/signature-requests');
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('signature_requests.archive');
        csrf_verify();
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];
        (new SignatureRequest())->restore($id);
        (new ActivityLog())->record('signature_request_restored', $_SESSION['user_id'] ?? null, 'signature_request', $id);
        flash('success', 'Processo restaurado.');
        $this->redirect('/signature-requests/' . $id);
    }

    public function pdf(array $params): void
    {
        AuthMiddleware::requirePermission('signature_requests.view');
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];

        if ((string) ($item['status'] ?? '') !== 'assinado') {
            flash('error', 'O PDF só fica disponível após todas as partes assinarem.');
            $this->redirect('/signature-requests/' . $id);
            return;
        }

        $file = (new SignatureRequest())->resolveSignedPdfDownload($id);
        if ($file === null) {
            flash('error', 'Não foi possível gerar o PDF deste contrato.');
            $this->redirect('/signature-requests/' . $id);
            return;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._\-]+/', '_', $file['name']) ?: 'contrato-assinado.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . (string) filesize($file['path']));
        readfile($file['path']);
        exit;
    }

    public function signContratante(array $params): void
    {
        AuthMiddleware::requirePermission('signature_requests.send');
        csrf_verify();
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];
        $model = new SignatureRequest();

        if (!in_array((string) ($item['status'] ?? ''), ['aguardando_assinatura', 'parcialmente_assinado'], true)) {
            flash('error', 'Este processo não está aguardando assinatura da contratante.');
            $this->redirect('/signature-requests/' . $id);
            return;
        }

        $contratante = $model->findContratanteSigner($id);
        if ($contratante === null || (string) ($contratante['status'] ?? '') === 'assinado') {
            flash('error', 'Não há assinatura pendente da contratante neste processo.');
            $this->redirect('/signature-requests/' . $id);
            return;
        }

        if (input('accept_terms') !== '1') {
            flash('error', 'É necessário confirmar a assinatura em nome da contratante.');
            $this->redirect('/signature-requests/' . $id);
            return;
        }

        $adminName = trim((string) ($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Administrador'));
        $legalName = trim((string) ($contratante['signer_name'] ?? 'JA PRODUÇÕES ARTÍSTICAS LTDA'));
        $acceptanceText = 'Assinado eletronicamente em nome da CONTRATANTE (' . $legalName . ') por '
            . $adminName . ', administrador autorizado do sistema Dança Carajás Captação.';

        $model->sign((int) $contratante['id'], $legalName, $acceptanceText);
        (new ActivityLog())->record('signature_contratante_signed', $_SESSION['user_id'] ?? null, 'signature_request', $id);

        flash('success', 'Assinatura da contratante (JA Produções) registrada. Contrato concluído.');
        $this->redirect('/signature-requests/' . $id);
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $row = (new SignatureRequest())->findById($id ?? 0);
        if ($row === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Não encontrado']);
            exit;
        }

        return $row;
    }
}
