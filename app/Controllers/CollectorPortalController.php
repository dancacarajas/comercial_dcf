<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Collector;
use App\Models\CollectorApplication;
use App\Models\CollectorAssignment;
use App\Models\CollectorDeal;
use App\Models\Company;
use App\Models\Contact;
use App\Services\CollectorProspectIntake;

/**
 * Portal do Captador (Etapa 18C — Fase 2B).
 *
 * Área exclusiva do captador externo aprovado para montar a própria carteira:
 * cadastrar prospects, registrar contatos e andamento, e acompanhar suas
 * captações. Todo o acesso é limitado ao captador autenticado (escopo por
 * collectors.user_id). O captador nunca enxerga dados de outros captadores.
 */
final class CollectorPortalController extends Controller
{
    /** @return array<string,mixed>|null */
    private function currentCollector(): ?array
    {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            return null;
        }

        return (new Collector())->findByUserId($uid);
    }

    /**
     * Exige permissão de portal e vínculo a um captador.
     *
     * @return array<string,mixed>
     */
    private function guard(): array
    {
        AuthMiddleware::requirePermission('collector_portal.view');

        $collector = $this->currentCollector();
        if ($collector === null) {
            $this->denyPortal('Sua conta ainda nao esta vinculada a um cadastro de captador aprovado.');
        }

        /** @var array<string,mixed> $collector */
        $reason = $this->accreditationBlock($collector);
        if ($reason !== null) {
            $this->denyPortal($reason);
        }

        return $collector;
    }

    private function denyPortal(string $message): void
    {
        http_response_code(403);
        $this->view('portal/no_access', [
            'title'   => 'Portal do Captador',
            'message' => $message,
        ], 'layouts/portal');
        exit;
    }

    /**
     * Defesa em camadas: o captador so acessa o portal com credenciamento
     * concluido (ativo, validado, candidatura aprovada e assinaturas
     * obrigatorias finalizadas quando houver candidatura vinculada).
     *
     * @param array<string,mixed> $collector
     */
    private function accreditationBlock(array $collector): ?string
    {
        $denied = 'Seu acesso ao Portal do Captador ainda nao esta liberado. Conclua o credenciamento e as assinaturas obrigatorias.';

        if ((string) ($collector['status'] ?? '') !== 'ativo') {
            return $denied;
        }
        if ((string) ($collector['registration_status'] ?? '') !== 'validado') {
            return $denied;
        }

        $appId = (int) ($collector['collector_application_id'] ?? 0);
        if ($appId > 0) {
            $appModel = new CollectorApplication();
            $app = $appModel->findById($appId);
            if ($app === null) {
                return $denied;
            }
            $approved = (string) ($app['review_status'] ?? '') === 'aprovado'
                || in_array((string) ($app['status'] ?? ''), ['aprovado', 'aguardando_assinatura_contratual', 'contrato_assinado', 'acesso_preparado', 'acesso_liberado'], true);
            if (!$approved) {
                return $denied;
            }
            if (!$appModel->hasCompletedRequiredCollectorSignatures($app)) {
                return $denied;
            }
        }

        return null;
    }

    public function dashboard(): void
    {
        $collector = $this->guard();

        $assignmentModel = new CollectorAssignment();
        $dealModel       = new CollectorDeal();

        $assignments = $assignmentModel->forCollector((int) $collector['id']);
        $deals       = $dealModel->forCollector((int) $collector['id']);

        $this->view('portal/dashboard', [
            'title'         => 'Minha carteira',
            'collector'     => $collector,
            'assignments'   => $assignments,
            'deals'         => $deals,
            'assignTypes'   => $assignmentModel->getTypes(),
            'assignStatuses'=> $assignmentModel->getStatuses(),
            'dealStatuses'  => $dealModel->getStatuses(),
        ], 'layouts/portal');
    }

    public function prospectCreate(): void
    {
        $collector = $this->guard();
        $companyModel = new Company();

        $this->view('portal/prospect_form', [
            'title'     => 'Novo prospect',
            'collector' => $collector,
            'data'      => [],
            'errors'    => [],
            'segments'  => $companyModel->getSegments(),
            'states'    => $companyModel->getStates(),
        ], 'layouts/portal');
    }

    public function prospectStore(): void
    {
        $collector = $this->guard();
        AuthMiddleware::requirePermission('collector_portal.companies.create');
        csrf_verify();

        $companyModel = new Company();
        $data = [
            'name'    => clean((string) input('name', '')),
            'cnpj'    => clean((string) input('cnpj', '')),
            'segment' => clean((string) input('segment', '')),
            'city'    => clean((string) input('city', '')),
            'state'   => clean((string) input('state', '')),
            'email'   => clean((string) input('email', '')),
            'phone'   => clean((string) input('phone', '')),
            'notes'   => trim((string) input('notes', '')),
        ];

        $errors = [];
        if ($data['name'] === '' || mb_strlen($data['name']) < 2) {
            $errors['name'] = 'Informe o nome da empresa/prospect (mínimo 2 caracteres).';
        }
        $cnpjN = $companyModel->normalizeCnpj($data['cnpj']);
        if ($data['cnpj'] !== '' && strlen($cnpjN) !== 14) {
            $errors['cnpj'] = 'CNPJ deve conter 14 dígitos.';
        }
        if ($data['email'] !== '' && !is_email($data['email'])) {
            $errors['email'] = 'E-mail inválido.';
        }
        if ($data['state'] !== '' && !preg_match('/^[A-Za-z]{2}$/', $data['state'])) {
            $errors['state'] = 'UF deve ter 2 letras.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->view('portal/prospect_form', [
                'title'     => 'Novo prospect',
                'collector' => $collector,
                'data'      => $data,
                'errors'    => $errors,
                'segments'  => $companyModel->getSegments(),
                'states'    => $companyModel->getStates(),
            ], 'layouts/portal');
            return;
        }

        $res = (new CollectorProspectIntake())->intake($collector, $data, (int) ($_SESSION['user_id'] ?? 0));

        if (in_array($res['status'], ['criado', 'analise_interna'], true)) {
            flash('success', $res['message']);
            $this->redirect('/portal/deals/' . (int) $res['deal_id']);
            return;
        }

        if ($res['status'] === 'ja_na_carteira') {
            flash('info', $res['message']);
            $this->redirect('/portal');
            return;
        }

        // bloqueado / análise interna sem assunção automática
        flash('error', $res['message']);
        $this->redirect('/portal');
    }

    public function dealShow(array $params): void
    {
        $collector = $this->guard();

        $dealModel = new CollectorDeal();
        $deal = $dealModel->findById((int) ($params['id'] ?? 0));
        if ($deal === null || (int) $deal['collector_id'] !== (int) $collector['id']) {
            $this->abort(404, 'Captação não encontrada na sua carteira.');
        }

        $contacts = (new Contact())->findByCompanyForPortal((int) $deal['company_id'], (int) ($_SESSION['user_id'] ?? 0), 50);

        $this->view('portal/deal_show', [
            'title'        => 'Captação — ' . (string) ($deal['company_name'] ?? ''),
            'collector'    => $collector,
            'deal'         => $deal,
            'contacts'     => $contacts,
            'dealStatuses' => $dealModel->getStatuses(),
        ], 'layouts/portal');
    }

    public function dealNote(array $params): void
    {
        $collector = $this->guard();
        AuthMiddleware::requirePermission('collector_portal.deals.note');
        csrf_verify();

        $dealModel = new CollectorDeal();
        $deal = $dealModel->findById((int) ($params['id'] ?? 0));
        if ($deal === null || (int) $deal['collector_id'] !== (int) $collector['id']) {
            $this->abort(404, 'Captação não encontrada na sua carteira.');
        }

        $note = trim((string) input('note', ''));
        if ($note !== '') {
            $prev  = trim((string) ($deal['notes'] ?? ''));
            $stamp = '[' . date('Y-m-d H:i') . '] ' . $note;
            $merged = $prev === '' ? $stamp : ($prev . "\n" . $stamp);
            $dealModel->update((int) $deal['id'], ['notes' => $merged]);
            (new ActivityLog())->record('collector_portal_deal_note', $_SESSION['user_id'] ?? null, 'collector_deal', (int) $deal['id']);
            flash('success', 'Observação registrada.');
        }

        $this->redirect('/portal/deals/' . (int) $deal['id']);
    }

    public function contactStore(array $params): void
    {
        $collector = $this->guard();
        AuthMiddleware::requirePermission('collector_portal.contacts.create');
        csrf_verify();

        $companyId = (int) ($params['id'] ?? 0);
        if (!$this->ownsCompany($collector, $companyId)) {
            $this->abort(403, 'Empresa fora da sua carteira.');
        }

        $name = clean((string) input('name', ''));
        if ($name === '' || mb_strlen($name) < 2) {
            flash('error', 'Informe o nome do contato.');
            $this->redirectBackToCompany($collector, $companyId);
            return;
        }

        $email = clean((string) input('email', ''));
        if ($email !== '' && !is_email($email)) {
            flash('error', 'E-mail do contato inválido.');
            $this->redirectBackToCompany($collector, $companyId);
            return;
        }

        (new Contact())->create([
            'company_id'        => $companyId,
            'name'              => $name,
            'position_title'    => clean((string) input('position_title', '')),
            'email'             => $email,
            'whatsapp'          => preg_replace('/\D+/', '', (string) input('whatsapp', '')),
            'phone'             => clean((string) input('phone', '')),
            'decision_level'    => 'nao_informado',
            'influence_level'   => 'desconhecida',
            'preferred_channel' => 'nao_informado',
            'status'            => 'ativo',
            'owner_user_id'     => $_SESSION['user_id'] ?? null,
            'created_by'        => $_SESSION['user_id'] ?? null,
        ]);

        (new ActivityLog())->record('collector_portal_contact_created', $_SESSION['user_id'] ?? null, 'company', $companyId);
        flash('success', 'Contato cadastrado.');
        $this->redirectBackToCompany($collector, $companyId);
    }

    private function redirectBackToCompany(array $collector, int $companyId): void
    {
        $deal = (new CollectorDeal())->findByCompanyForCollector($companyId, (int) $collector['id']);
        if ($deal !== null) {
            $this->redirect('/portal/deals/' . (int) $deal['id']);
            return;
        }
        $this->redirect('/portal');
    }

    private function ownsCompany(array $collector, int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }
        foreach ((new CollectorAssignment())->forCompany($companyId) as $row) {
            if ((int) $row['collector_id'] === (int) $collector['id'] && empty($row['archived_at'])) {
                return true;
            }
        }
        return false;
    }
}
