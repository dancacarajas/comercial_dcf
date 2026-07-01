<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\CollectorAssignment;
use App\Models\CollectorDeal;
use App\Models\Company;
use PDO;

/**
 * Intake de prospects pelo Portal do Captador (Etapa 18C — Fase 2B).
 *
 * Recebe o cadastro de uma empresa/prospect feito pelo próprio captador externo,
 * verifica duplicidade e conflito e decide:
 *  - SEM conflito: cria/vincula company + collector_assignment + collector_deal
 *    (lead_indicado) com origem "portal_captador";
 *  - conflito BRANDO (oportunidade interna ativa / nome semelhante): cria a
 *    carteira mas marca o deal como empresa_em_analise para revisão do admin;
 *  - conflito FORTE (exclusiva de outro captador / já patrocinadora): bloqueia e
 *    encaminha para análise interna, sem deixar o captador assumir a empresa.
 */
final class CollectorProspectIntake
{
    public const ORIGIN = 'portal_captador';

    /** Tokens genéricos ignorados na detecção de nome semelhante. */
    private const STOPWORDS = [
        'teste', 'empresa', 'grupo', 'companhia', 'industria', 'comercio',
        'servicos', 'servico', 'brasil', 'sociedade', 'participacoes',
        'holding', 'ltda', 'eireli', 'epp', 'me', 'sa',
    ];

    /**
     * @param array<string,mixed> $collector  linha do captador (collectors)
     * @param array<string,mixed> $input       dados do formulário do portal
     * @param ?int                 $projectId   projeto incentivado escolhido (Etapa 19)
     * @return array{status:string,message:string,company_id:?int,assignment_id:?int,deal_id:?int,conflicts:array<int,string>}
     */
    public function intake(array $collector, array $input, ?int $userId, ?int $projectId = null): array
    {
        $companyModel = new Company();
        $collectorId  = (int) ($collector['id'] ?? 0);

        $name = trim((string) ($input['name'] ?? ''));
        $cnpj = $companyModel->normalizeCnpj((string) ($input['cnpj'] ?? ''));

        // 1) Empresa já existe? CNPJ tem prioridade; senão nome idêntico.
        $existing = null;
        if ($cnpj !== '') {
            $existing = $this->findCompanyByCnpj($cnpj);
        }
        if ($existing === null && $name !== '') {
            $existing = $this->findCompanyByExactName($name);
        }

        $companyId = $existing !== null ? (int) $existing['id'] : null;
        $hard = [];
        $soft = [];

        if ($companyId !== null) {
            // Já está na carteira deste próprio captador (no mesmo projeto).
            if ($this->hasActiveAssignment($companyId, $collectorId, $projectId)) {
                return $this->result(
                    'ja_na_carteira',
                    'Esta empresa já está na sua carteira para este projeto.',
                    $companyId,
                    null,
                    null,
                    []
                );
            }
            // Conflito forte: exclusiva ativa de OUTRO captador no mesmo projeto.
            if ($this->activeExclusiveByOther($companyId, $collectorId, $projectId) !== null) {
                $hard[] = 'Empresa já possui atribuição exclusiva ativa de outro captador neste projeto.';
            }
            // Conflito forte: empresa já é patrocinadora confirmada deste projeto.
            if ($this->isActiveSponsor($companyId, $projectId)) {
                $hard[] = 'Empresa já é patrocinadora confirmada deste projeto.';
            }
            // Conflito brando: já em oportunidade interna ativa neste projeto.
            if ($this->hasActiveInternalOpportunity($companyId, $projectId)) {
                $soft[] = 'Empresa já possui oportunidade interna em andamento neste projeto.';
            }
            // Conflito brando: empresa já patrocinou outro projeto anterior.
            if ($projectId !== null && $this->sponsoredOtherProject($companyId, $projectId)) {
                $soft[] = 'Empresa já patrocinou outro projeto/edição anteriormente.';
            }
        } elseif ($name !== '') {
            // Empresa nova: alerta de possível duplicidade por nome semelhante.
            $similar = $this->findSimilarCompany($name);
            if ($similar !== null) {
                $soft[] = 'Possível duplicidade: nome semelhante a "' . (string) $similar['name'] . '" já cadastrada.';
            }
        }

        // 2) Conflito forte -> bloqueia e encaminha para análise interna.
        if ($hard !== []) {
            (new ActivityLog())->record('collector_portal_prospect_blocked', $userId, 'company', $companyId);

            return $this->result(
                'bloqueado',
                'Não foi possível adicionar esta empresa automaticamente à sua carteira. '
                . 'O pedido foi encaminhado para análise da equipe Dança Carajás. Motivo: '
                . implode(' ', $hard),
                $companyId,
                null,
                null,
                $hard
            );
        }

        // 3) Sem empresa existente -> cria o prospect.
        if ($companyId === null) {
            $companyId = (int) $companyModel->create([
                'name'          => $name,
                'cnpj'          => $cnpj,
                'segment'       => $this->safeSegment($companyModel, (string) ($input['segment'] ?? '')),
                'city'          => trim((string) ($input['city'] ?? '')),
                'state'         => strtoupper(trim((string) ($input['state'] ?? ''))),
                'general_email' => $this->safeEmail((string) ($input['email'] ?? '')),
                'general_phone' => trim((string) ($input['phone'] ?? '')),
                'priority'      => 'C',
                'status'        => 'prospect',
                'source'        => 'indicação interna',
                'owner_user_id' => $userId,
                'notes'         => 'Origem: Portal do Captador — ' . (string) ($collector['name'] ?? '')
                    . ' (' . (string) ($collector['collector_code'] ?? '') . ').',
            ]);
            (new ActivityLog())->record('collector_portal_company_created', $userId, 'company', $companyId);
        }

        $emAnalise  = $soft !== [];
        $dealStatus = $emAnalise ? 'empresa_em_analise' : 'lead_indicado';

        // 4) Cria a atribuição (reserva) e o deal (rastreabilidade), origem portal.
        $assignmentId = (int) (new CollectorAssignment())->create([
            'incentive_project_id' => $projectId,
            'collector_id'    => $collectorId,
            'company_id'      => $companyId,
            'assignment_type' => 'exclusiva',
            'status'          => 'solicitada',
            'created_by'      => $userId,
            'notes'           => '[ORIGEM:portal_captador] Solicitada pelo captador via portal.',
        ]);

        $dealId = (int) (new CollectorDeal())->create([
            'incentive_project_id'    => $projectId,
            'collector_id'            => $collectorId,
            'collector_assignment_id' => $assignmentId,
            'company_id'              => $companyId,
            'deal_status'             => $dealStatus,
            'attribution_type'        => 'direta',
            'source'                  => self::ORIGIN,
            'created_by'              => $userId,
            'notes'                   => trim((string) ($input['notes'] ?? '')),
        ]);

        $log = new ActivityLog();
        $log->record('collector_portal_prospect_added', $userId, 'collector_assignment', $assignmentId);
        $log->record('collector_deal_created', $userId, 'collector_deal', $dealId);

        if ($emAnalise) {
            return $this->result(
                'analise_interna',
                'Empresa adicionada à sua carteira e marcada para análise interna. ' . implode(' ', $soft),
                $companyId,
                $assignmentId,
                $dealId,
                $soft
            );
        }

        return $this->result(
            'criado',
            'Prospect adicionado à sua carteira com sucesso.',
            $companyId,
            $assignmentId,
            $dealId,
            []
        );
    }

    /**
     * @param array<int,string> $conflicts
     * @return array{status:string,message:string,company_id:?int,assignment_id:?int,deal_id:?int,conflicts:array<int,string>}
     */
    private function result(string $status, string $message, ?int $companyId, ?int $assignmentId, ?int $dealId, array $conflicts): array
    {
        return [
            'status'        => $status,
            'message'       => $message,
            'company_id'    => $companyId,
            'assignment_id' => $assignmentId,
            'deal_id'       => $dealId,
            'conflicts'     => $conflicts,
        ];
    }

    /** @return array<string,mixed>|null */
    private function findCompanyByCnpj(string $cnpj): ?array
    {
        $row = Database::run(
            'SELECT `id`, `name`, `cnpj` FROM `companies` WHERE `cnpj` = :c AND `archived_at` IS NULL LIMIT 1',
            ['c' => $cnpj]
        )->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function findCompanyByExactName(string $name): ?array
    {
        $row = Database::run(
            'SELECT `id`, `name` FROM `companies` WHERE `name` = :n AND `archived_at` IS NULL LIMIT 1',
            ['n' => $name]
        )->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Detecta empresa com nome semelhante (possível duplicidade), ignorando
     * tokens genéricos. Usa o maior token significativo para pré-filtrar e
     * similar_text para confirmar a proximidade.
     *
     * @return array<string,mixed>|null
     */
    private function findSimilarCompany(string $name): ?array
    {
        $normTarget = $this->normalizeName($name);
        if ($normTarget === '') {
            return null;
        }

        $tokens = array_filter(
            explode(' ', $normTarget),
            fn (string $t): bool => mb_strlen($t) >= 4 && !in_array($t, self::STOPWORDS, true)
        );
        if ($tokens === []) {
            return null;
        }
        usort($tokens, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $probe = $tokens[0];

        $rows = Database::run(
            'SELECT `id`, `name` FROM `companies` WHERE `name` LIKE :q AND `archived_at` IS NULL LIMIT 50',
            ['q' => '%' . $probe . '%']
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $normRow = $this->normalizeName((string) $row['name']);
            if ($normRow === '' || $normRow === $normTarget) {
                continue;
            }
            similar_text($normTarget, $normRow, $percent);
            if ($percent >= 80.0
                || (mb_strlen($probe) >= 5 && str_contains($normRow, $probe))) {
                return $row;
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        $s = mb_strtolower(trim($name), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
        $s = preg_replace('/[^a-z0-9 ]/', ' ', $s) ?? '';
        $s = preg_replace('/\b(ltda|eireli|epp|sa|me|s a|s\/a)\b/', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return trim($s);
    }

    private function hasActiveAssignment(int $companyId, int $collectorId, ?int $projectId): bool
    {
        $sql = "SELECT `id` FROM `collector_assignments`
              WHERE `company_id` = :co AND `collector_id` = :cl
                AND `archived_at` IS NULL AND `status` IN ('solicitada','autorizada')";
        $params = ['co' => $companyId, 'cl' => $collectorId];
        if ($projectId !== null) {
            $sql .= ' AND `incentive_project_id` = :pj';
            $params['pj'] = $projectId;
        }
        $row = Database::run($sql . ' LIMIT 1', $params)->fetch(PDO::FETCH_ASSOC);

        return $row !== false;
    }

    /** @return array<string,mixed>|null */
    private function activeExclusiveByOther(int $companyId, int $collectorId, ?int $projectId): ?array
    {
        $sql = "SELECT `id`, `collector_id` FROM `collector_assignments`
              WHERE `company_id` = :co AND `collector_id` <> :cl
                AND `assignment_type` = 'exclusiva' AND `archived_at` IS NULL
                AND `status` IN ('solicitada','autorizada')
                AND (`exclusive_until` IS NULL OR `exclusive_until` >= CURDATE())";
        $params = ['co' => $companyId, 'cl' => $collectorId];
        if ($projectId !== null) {
            $sql .= ' AND `incentive_project_id` = :pj';
            $params['pj'] = $projectId;
        }
        $row = Database::run($sql . ' ORDER BY `id` DESC LIMIT 1', $params)->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function isActiveSponsor(int $companyId, ?int $projectId): bool
    {
        $sql = "SELECT `id` FROM `sponsors`
              WHERE `company_id` = :co AND `archived_at` IS NULL
                AND `status` <> 'cancelado'";
        $params = ['co' => $companyId];
        if ($projectId !== null) {
            $sql .= ' AND `incentive_project_id` = :pj';
            $params['pj'] = $projectId;
        }
        $row = Database::run($sql . ' LIMIT 1', $params)->fetch(PDO::FETCH_ASSOC);

        return $row !== false;
    }

    /** Empresa que já patrocinou algum projeto DIFERENTE do atual (conflito brando). */
    private function sponsoredOtherProject(int $companyId, int $projectId): bool
    {
        $row = Database::run(
            "SELECT `id` FROM `sponsors`
              WHERE `company_id` = :co AND `archived_at` IS NULL
                AND `status` <> 'cancelado'
                AND `incentive_project_id` IS NOT NULL
                AND `incentive_project_id` <> :pj LIMIT 1",
            ['co' => $companyId, 'pj' => $projectId]
        )->fetch(PDO::FETCH_ASSOC);

        return $row !== false;
    }

    private function hasActiveInternalOpportunity(int $companyId, ?int $projectId): bool
    {
        $sql = "SELECT `id` FROM `opportunities`
              WHERE `company_id` = :co AND `archived_at` IS NULL
                AND `status` NOT IN ('fechado','perdido')";
        $params = ['co' => $companyId];
        if ($projectId !== null) {
            $sql .= ' AND `incentive_project_id` = :pj';
            $params['pj'] = $projectId;
        }
        $row = Database::run($sql . ' LIMIT 1', $params)->fetch(PDO::FETCH_ASSOC);

        return $row !== false;
    }

    private function safeSegment(Company $model, string $segment): string
    {
        $segment = trim($segment);

        return in_array($segment, $model->getSegments(), true) ? $segment : '';
    }

    private function safeEmail(string $email): string
    {
        $email = trim($email);

        return ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : '';
    }
}
