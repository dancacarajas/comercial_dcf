<?php
declare(strict_types=1);

/**
 * Literais imutáveis Etapa 22.1 / border contract v1.2.
 */
final class Dcx_Ssp_Constants
{
    public const CRM_ENDPOINT = 'https://comercial.dancacarajas.com.br/api/leads/site';

    public const SUBMISSION_TYPE = 'SPONSORSHIP_SIMULATION';
    public const SUBMISSION_VERSION = '1.0.0';
    public const SNAPSHOT_VERSION = '1.0.0';
    public const BRIEFING_SCHEMA_VERSION = '1.0.0';
    public const CATALOG_VERSION = '2026-V2.1';

    public const PRONAC_NUMBER = '265397';
    public const EDITION_YEAR = 2026;

    public const ORIGIN_PAGE = 'patrocinio/seja-patrocinador';
    public const SOURCE_URL = 'https://dancacarajas.com.br/patrocinio/seja-patrocinador/';
    public const FORM_ID = 'dcx-sponsorship-simulation-v1';
    public const FORM_NAME = 'Simular Patrocinio';

    public const MAX_BODY_BYTES = 65536; // 64 KiB

    public const TOP_LEVEL_KEYS = [
        'submission_type',
        'submission_version',
        'submission_uuid',
        'name',
        'company_name',
        'role_title',
        'email',
        'whatsapp',
        'city',
        'state',
        'segment',
        'contact_consent',
        'message',
        'origin_page',
        'source_url',
        'form_id',
        'form_name',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'project',
        'sponsorship_simulation',
        'website',
        'website_url',
    ];

    public const HARD_DENY_KEYS = [
        'token',
        'lead_token',
        'api_key',
        'secret',
        'reason_codes',
        'internal_score',
        'diagnostics',
        'passport',
        'session_id',
        'quota_id',
        'opportunity_id',
        'sponsor_id',
        'lead_id',
        'simulation_id',
        'availability_label',
        'fit_label',
        'primary_tier_label',
        'investment_label',
        'approved_total_amount',
    ];

    public const DISPLAY_KEYS = [
        'objective_labels',
        'depth_label',
        'experience_labels',
        'proof_labels',
        'axis_label',
        'activation_label',
    ];

    public const DISPLAY_FORBIDDEN = [
        'availability_label',
        'fit_label',
        'primary_tier_label',
        'investment_label',
    ];

    public const PUBLIC_ERROR_KEYS = [
        'name',
        'company_name',
        'role_title',
        'email',
        'whatsapp',
        'message',
        'contact_consent',
        'city',
        'state',
        'segment',
    ];

    public const AREA_DECISIONS = [
        'MARKETING_COMMUNICATION',
        'ESG_IMPACT',
        'HR_PEOPLE',
        'INSTITUTIONAL_RELATIONS',
        'CLIENTS_STAKEHOLDERS',
        'EXECUTIVE_LEADERSHIP',
        'OTHER',
        'UNDEFINED',
    ];

    public const DEPTH_INTENTS = [
        'BELONG',
        'OWN_ACTION',
        'AUTHORIAL_CASE',
        'OWN_AXIS',
        'PRESENT_EDITION',
        'UNDEFINED',
    ];

    public const OBJECTIVES = [
        'INSTITUTIONAL_ASSOCIATION',
        'BRAND_PRESENCE',
        'CULTURAL_EXPERIENCE',
        'CONTENT_RELATIONSHIP',
        'ESG_CULTURAL_IMPACT',
        'EMPLOYEE_ENGAGEMENT',
        'CLIENT_STAKEHOLDER_RELATIONSHIP',
        'OWN_CULTURAL_TERRITORY',
        'EDITION_PROTAGONISM',
        'LEGACY',
        'UNDEFINED',
    ];

    public const AUDIENCES = [
        'GENERAL_PUBLIC',
        'COMMUNITY',
        'ARTISTS_GROUPS',
        'EMPLOYEES',
        'CLIENTS',
        'STAKEHOLDERS',
        'LEADERSHIP',
        'PARTNERS',
        'MULTIPLE',
        'UNDEFINED',
    ];

    public const PROOF_NEEDS = [
        'INSTITUTIONAL_DELIVERY',
        'PARTICIPATION_EVIDENCE',
        'CONTENT_CASE',
        'PORTAL',
        'AXIS_REPORT',
        'EXECUTIVE_REPORT',
        'AUTHORIZED_ARCHIVE',
        'LEGACY_CONTINUITY',
        'UNDEFINED',
    ];

    public const FIT_LEVELS = [
        'VERY_HIGH',
        'HIGH',
        'MODERATE',
        'LOW',
        'INCOMPATIBLE',
        'NOT_CALCULATED',
    ];

    public const INVESTMENT_STATUSES = [
        'UNDEFINED',
        'OPEN',
        'DEFINED_AMOUNT',
        'DEFINED_RANGE',
    ];
}
