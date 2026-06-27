<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use RuntimeException;

/**
 * Documentos do credenciamento de captadores.
 */
final class CollectorApplicationDocument extends Model
{
    protected string $table = 'collector_application_documents';

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = ['php', 'phtml', 'phar', 'exe', 'js', 'html', 'htm', 'svg', 'sh', 'bat', 'cmd', 'zip'];

    /** @var array<string, list<string>> */
    private const ALLOWED_MIMES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @return array<string, string> */
    public function getTypesIndividual(): array
    {
        return [
            'identidade'                 => 'Documento de identificação com foto',
            'cpf'                        => 'CPF (se não constar no documento)',
            'comprovante_endereco'       => 'Comprovante de endereço',
            'comprovante_bancario'       => 'Comprovante bancário',
            'curriculo_portfolio'        => 'Mini currículo ou apresentação profissional',
            'comprovacao_experiencia'    => 'Portfólio ou comprovação de experiência',
            'termo_confidencialidade'    => 'Termo de confidencialidade',
            'termo_autorizacao_captacao'  => 'Termo de autorização de captação',
            'outro'                      => 'Outro documento',
        ];
    }

    /** @return array<string, string> */
    public function getTypesLegalEntity(): array
    {
        return [
            'cartao_cnpj'                => 'Cartão CNPJ',
            'contrato_social_ou_mei'     => 'Ato de constituição da empresa',
            'documento_representante'    => 'Documento do representante legal',
            'comprovante_endereco'       => 'Comprovante de endereço',
            'comprovante_bancario'       => 'Comprovante bancário',
            'apresentacao_institucional' => 'Apresentação institucional',
            'portfolio_cases'            => 'Portfólio / cases',
            'termo_confidencialidade'    => 'Termo de confidencialidade',
            'termo_autorizacao_captacao'  => 'Termo de autorização de captação',
            'outro'                      => 'Outro documento',
        ];
    }

    /** @return list<string> */
    public function getDefaultTypeKeysIndividual(): array
    {
        return [
            'identidade',
            'comprovante_endereco',
            'comprovante_bancario',
            'curriculo_portfolio',
            'comprovacao_experiencia',
            'termo_confidencialidade',
            'termo_autorizacao_captacao',
        ];
    }

    /** @return list<string> */
    public function getDefaultTypeKeysLegalEntity(): array
    {
        return [
            'cartao_cnpj',
            'contrato_social_ou_mei',
            'comprovante_bancario',
            'documento_representante',
            'identidade',
            'comprovante_endereco',
            'curriculo_portfolio',
            'comprovacao_experiencia',
            'termo_confidencialidade',
            'termo_autorizacao_captacao',
        ];
    }

    /**
     * @param list<string> $keys
     * @return array<string, string>
     */
    public function labelsForTypeKeys(array $keys): array
    {
        $all = $this->getAllTypes();
        $out = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $out[$key] = $all[$key];
            }
        }

        return $out;
    }

    /** @return array<string, string> */
    public function getAllTypes(): array
    {
        return array_merge($this->getTypesIndividual(), $this->getTypesLegalEntity());
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'pendente'   => 'Pendente',
            'enviado'    => 'Enviado',
            'em_analise' => 'Em análise',
            'aprovado'   => 'Aprovado',
            'reprovado'  => 'Reprovado',
            'substituir' => 'Substituir',
            'arquivado'  => 'Arquivado',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByApplication(int $applicationId, bool $includeArchived = false): array
    {
        $sql = 'SELECT cad.*, u.`name` AS reviewed_by_name
                  FROM `collector_application_documents` cad
                  LEFT JOIN `users` u ON u.`id` = cad.`reviewed_by`
                 WHERE cad.`collector_application_id` = :aid';
        if (!$includeArchived) {
            $sql .= ' AND cad.`archived_at` IS NULL';
        }
        $sql .= ' ORDER BY cad.`id` ASC';

        return $this->query($sql, ['aid' => $applicationId])->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT * FROM `collector_application_documents` WHERE `id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    public function createSlots(int $applicationId, array $types): void
    {
        $all = $this->getAllTypes();
        foreach (array_unique($types) as $type) {
            $type = (string) $type;
            if (!array_key_exists($type, $all)) {
                continue;
            }
            $exists = $this->query(
                'SELECT `id` FROM `collector_application_documents`
                  WHERE `collector_application_id` = :aid AND `document_type` = :type AND `archived_at` IS NULL LIMIT 1',
                ['aid' => $applicationId, 'type' => $type]
            )->fetch();
            if ($exists) {
                continue;
            }
            $this->query(
                'INSERT INTO `collector_application_documents`
                    (`collector_application_id`, `document_type`, `title`, `status`, `created_at`)
                 VALUES (:aid, :type, :title, :status, NOW())',
                [
                    'aid'    => $applicationId,
                    'type'   => $type,
                    'title'  => $all[$type],
                    'status' => 'pendente',
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, string>
     */
    public function validateUpload(array $file): array
    {
        $errors = [];
        $errNo  = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errNo === UPLOAD_ERR_NO_FILE) {
            $errors['document_file'] = 'Selecione um arquivo.';

            return $errors;
        }
        if ($errNo !== UPLOAD_ERR_OK) {
            $errors['document_file'] = 'Falha no upload do arquivo.';

            return $errors;
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            $errors['document_file'] = 'O arquivo deve ter no máximo 10 MB.';
        }

        $name = (string) ($file['name'] ?? '');
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            $errors['document_file'] = 'Tipo de arquivo não permitido.';
            return $errors;
        }
        if (!array_key_exists($ext, self::ALLOWED_MIMES)) {
            $errors['document_file'] = 'Envie PDF, JPG, PNG ou DOCX.';
            return $errors;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp !== '' && is_uploaded_file($tmp)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo !== false ? (string) finfo_file($finfo, $tmp) : '';
            if ($finfo !== false) {
                finfo_close($finfo);
            }
            if ($mime !== '' && !in_array($mime, self::ALLOWED_MIMES[$ext], true)) {
                $errors['document_file'] = 'O conteúdo do arquivo não corresponde à extensão informada.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path:string,original_name:string,stored_name:string,extension:string,mime_type:string,size_bytes:int,checksum:string}
     */
    public function storeUpload(array $file): array
    {
        $baseDir = dirname(__DIR__, 2) . '/storage/uploads/collector_applications';
        $subDir  = date('Y') . '/' . date('m');
        $dir     = $baseDir . '/' . $subDir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $original = basename((string) ($file['name'] ?? 'documento.bin'));
        $original = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original) ?? 'documento.bin';
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $stored   = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest     = $dir . '/' . $stored;

        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new RuntimeException('Não foi possível salvar o arquivo.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo !== false ? (string) finfo_file($finfo, $dest) : 'application/octet-stream';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        return [
            'path'          => $dest,
            'original_name' => $original,
            'stored_name'   => $stored,
            'extension'     => $ext,
            'mime_type'     => $mime,
            'size_bytes'    => (int) filesize($dest),
            'checksum'      => hash_file('sha256', $dest) ?: '',
        ];
    }

    /**
     * @param array<string, mixed> $file
     */
    public function attachUpload(int $slotId, array $file, ?int $linkedDocumentId = null): void
    {
        $stored = $this->storeUpload($file);
        $this->query(
            'UPDATE `collector_application_documents`
                SET `uploaded_original_name` = :orig, `uploaded_stored_name` = :stored, `file_path` = :path,
                    `file_mime` = :mime, `file_size` = :size, `file_extension` = :ext, `checksum` = :checksum,
                    `document_id` = :doc_id, `status` = :status, `uploaded_at` = NOW(), `updated_at` = NOW()
              WHERE `id` = :id',
            [
                'orig'    => $stored['original_name'],
                'stored'  => $stored['stored_name'],
                'path'    => $stored['path'],
                'mime'    => $stored['mime_type'],
                'size'    => $stored['size_bytes'],
                'ext'     => $stored['extension'],
                'checksum'=> $stored['checksum'],
                'doc_id'  => $linkedDocumentId,
                'status'  => 'enviado',
                'id'      => $slotId,
            ]
        );
    }

    public function review(int $slotId, string $status, ?string $notes, int|string|null $userId): void
    {
        if (!array_key_exists($status, $this->getStatuses())) {
            throw new RuntimeException('Status documental inválido.');
        }
        $this->query(
            'UPDATE `collector_application_documents`
                SET `status` = :status, `review_notes` = :notes, `reviewed_by` = :uid,
                    `reviewed_at` = NOW(), `updated_at` = NOW()
              WHERE `id` = :id',
            ['status' => $status, 'notes' => $notes, 'uid' => $userId, 'id' => $slotId]
        );
    }
}
