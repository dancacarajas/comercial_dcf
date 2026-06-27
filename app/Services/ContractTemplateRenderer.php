<?php



declare(strict_types=1);



namespace App\Services;



/**

 * Renderiza modelos de contrato com placeholders dinâmicos e sanitização HTML.

 */

final class ContractTemplateRenderer

{

    /** @var list<string> */

    private const ALLOWED_TAGS = [

        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'h4',

        'ul', 'ol', 'li', 'blockquote', 'div', 'span', 'table', 'thead', 'tbody', 'tr', 'th', 'td',

    ];



    /**

     * @param array<string, mixed> $context

     */

    public function render(string $html, array $context = []): string

    {

        $flat = $this->flattenContext($context);

        $out  = preg_replace_callback(

            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',

            static function (array $m) use ($flat): string {

                $key = $m[1];

                $val = $flat[$key] ?? '';



                return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            },

            $html

        ) ?? $html;



        return $this->sanitizeHtml($out);

    }



    public function toPlainText(string $html): string

    {

        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $html));



        return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);

    }



    /** @return array<string, string> */

    public static function defaultPlaceholders(): array

    {

        return [

            'collector.name'                    => 'Nome do captador',

            'collector.legal_type'              => 'Pessoa física / jurídica',

            'collector.document_number'         => 'CPF/CNPJ',

            'collector.email'                   => 'E-mail',

            'collector.phone_whatsapp'          => 'WhatsApp',

            'collector.city_state'              => 'Cidade/UF',

            'collector.company_or_activity'     => 'Empresa/atuação',

            'collector.rouanet_experience'      => 'Experiência Rouanet',

            'collector.segments'                => 'Segmentos',

            'application.application_number'    => 'Nº candidatura',

            'application.approved_at'           => 'Data aprovação',

            'application.created_at'            => 'Data candidatura',

            'organization.name'                 => 'Organização',

            'organization.document'             => 'CNPJ organização',

            'organization.address'              => 'Endereço organização',

            'organization.email'                => 'E-mail organização',

            'organization.phone'                => 'Telefone organização',

            'organization.representative_name'  => 'Representante',

            'contract.title'                    => 'Título do contrato',

            'contract.issue_date'               => 'Data emissão',

            'contract.start_date'               => 'Início vigência',

            'contract.end_date'                 => 'Término vigência',

            'contract.forum'                    => 'Foro',

            'compensation.percentage'           => 'Percentual remuneração',

            'compensation.payment_term'         => 'Prazo pagamento',

            'compensation.payment_method'       => 'Forma pagamento',

            'compensation.notes'                => 'Observações remuneração',

            'exclusivity.type'                  => 'Tipo exclusividade',

            'exclusivity.scope'                 => 'Escopo exclusividade',

            'exclusivity.period'                => 'Período exclusividade',

            'signature.request_number'          => 'Nº solicitação assinatura',

            'signature.signed_at'               => 'Data/hora assinatura',

            'signature.verification_code'       => 'Código verificação',

            'signature.hash'                    => 'Hash assinatura',

            'date.today'                        => 'Data atual',

        ];

    }



    /**

     * @param array<string, mixed> $application

     * @param array<string, mixed> $orgConfig

     * @param array<string, mixed> $options

     * @return array<string, mixed>

     */

    public static function contextFromCollectorApplication(

        array $application,

        array $orgConfig = [],

        array $options = []

    ): array {

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';

        $contractCfg = (array) ($appConfig['contract'] ?? []);

        $legal = (array) ($orgConfig['legal_entity'] ?? []);



        $doc = (string) ($application['document_number'] ?? '');

        $today = new \DateTimeImmutable('today');

        $months = max(1, (int) ($contractCfg['default_duration_months'] ?? 12));

        $endDate = $today->modify('+' . $months . ' months');



        $pendingSignature = (string) ($options['pending_signature_label']

            ?? 'A ser registrado na conclusão da assinatura eletrônica');



        $appNumber = (string) ($application['application_number'] ?? '');

        if ($appNumber === '' && !empty($application['id'])) {

            $appNumber = 'CAP-' . (int) $application['id'];

        }



        return [

            'collector' => [

                'name'                => (string) ($application['name'] ?? ''),

                'legal_type'          => self::inferLegalType($doc),

                'document_number'     => $doc,

                'email'               => (string) ($application['email'] ?? ''),

                'phone_whatsapp'      => (string) ($application['phone_whatsapp'] ?? ''),

                'city_state'          => (string) ($application['city_state'] ?? ''),

                'company_or_activity' => (string) ($application['company_or_activity'] ?? ''),

                'rouanet_experience'  => self::labelRouanetExperience((string) ($application['rouanet_experience'] ?? '')),

                'segments'            => (string) ($application['segments'] ?? ''),

            ],

            'application' => [

                'application_number' => $appNumber,

                'approved_at'        => self::formatDateBr((string) ($application['approved_at'] ?? '')),

                'created_at'         => self::formatDateBr((string) ($application['created_at'] ?? '')),

            ],

            'organization' => [

                'name'                => (string) ($orgConfig['name'] ?? 'Dança Carajás Festival'),

                'document'            => (string) ($legal['document'] ?? $orgConfig['document'] ?? '40.041.396/0001-30'),

                'address'             => (string) ($orgConfig['address'] ?? ''),

                'email'               => (string) ($orgConfig['email'] ?? ''),

                'phone'               => (string) ($orgConfig['phone'] ?? ''),

                'representative_name' => (string) ($orgConfig['representative_name'] ?? ''),

            ],

            'contract' => [

                'title'       => (string) ($options['contract_title'] ?? 'Contrato de Captação — Captador Externo'),

                'issue_date'  => $today->format('d/m/Y'),

                'start_date'  => $today->format('d/m/Y'),

                'end_date'    => $endDate->format('d/m/Y'),

                'forum'       => (string) ($contractCfg['default_forum'] ?? 'Parauapebas/PA'),

            ],

            'compensation' => [

                'percentage'     => (string) ($contractCfg['default_compensation_percentage'] ?? '10'),

                'payment_term'   => (string) ($contractCfg['default_payment_term'] ?? '30 (trinta) dias após o recebimento dos recursos pela CONTRATANTE'),

                'payment_method' => (string) ($contractCfg['default_payment_method'] ?? 'transferência bancária'),

                'notes'          => (string) ($contractCfg['default_compensation_notes'] ?? 'Conforme termo aditivo ou autorização comercial específica, quando aplicável.'),

            ],

            'exclusivity' => [

                'type'   => (string) ($contractCfg['default_exclusivity_type'] ?? 'Não exclusiva (salvo termo aditivo específico)'),

                'scope'  => (string) ($contractCfg['default_exclusivity_scope'] ?? 'Conforme autorização comercial da CONTRATANTE'),

                'period' => (string) ($contractCfg['default_exclusivity_period'] ?? 'Conforme vigência contratual'),

            ],

            'signature' => [

                'request_number'    => $appNumber !== '' ? $appNumber : $pendingSignature,

                'signed_at'         => $pendingSignature,

                'verification_code' => $pendingSignature,

                'hash'              => $pendingSignature,

            ],

            'date' => [

                'today' => $today->format('d/m/Y'),

            ],

        ];

    }



    private static function inferLegalType(string $document): string

    {

        $digits = preg_replace('/\D+/', '', $document) ?? '';

        if (strlen($digits) === 14) {

            return 'pessoa jurídica';

        }

        if (strlen($digits) === 11) {

            return 'pessoa física';

        }



        return 'inscrito(a) no documento informado';

    }



    private static function labelRouanetExperience(string $value): string

    {

        return match ($value) {

            'nenhuma'       => 'sem experiência prévia com Lei Rouanet',

            'basica'        => 'experiência básica com Lei Rouanet',

            'intermediaria' => 'experiência intermediária com Lei Rouanet',

            'avancada'      => 'experiência avançada com Lei Rouanet',

            default         => $value,

        };

    }



    private static function formatDateBr(string $value): string

    {

        $value = trim($value);

        if ($value === '') {

            return '';

        }

        try {

            return (new \DateTimeImmutable($value))->format('d/m/Y');

        } catch (\Throwable) {

            return $value;

        }

    }



    /**

     * @param array<string, mixed> $context

     * @return array<string, string>

     */

    private function flattenContext(array $context, string $prefix = ''): array

    {

        $out = [];

        foreach ($context as $key => $value) {

            $k = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {

                $out = array_merge($out, $this->flattenContext($value, $k));

            } else {

                $out[$k] = (string) $value;

            }

        }



        return $out;

    }



    private function sanitizeHtml(string $html): string

    {

        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;

        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;

        $html = preg_replace('/\s(on\w+|style|javascript:)[^>]*/i', '', $html) ?? $html;



        return strip_tags($html, '<' . implode('><', self::ALLOWED_TAGS) . '>');

    }

}


