<?php
declare(strict_types=1);

final class Dcx_Ssp_Envelope_B
{
    /**
     * @param array<string, mixed> $validated Canonicalized A fields from validator
     * @return array<string, mixed>
     */
    public static function build(array $validated): array
    {
        $sim = $validated['sponsorship_simulation'];
        if (!is_array($sim)) {
            $sim = [];
        }

        $sim['snapshot_version'] = Dcx_Ssp_Constants::SNAPSHOT_VERSION;
        $sim['catalog_version'] = Dcx_Ssp_Constants::CATALOG_VERSION;
        $sim['briefing_schema_version'] = Dcx_Ssp_Constants::BRIEFING_SCHEMA_VERSION;

        $out = [
            'submission_type' => Dcx_Ssp_Constants::SUBMISSION_TYPE,
            'submission_version' => Dcx_Ssp_Constants::SUBMISSION_VERSION,
            'submission_uuid' => $validated['submission_uuid'],
            'name' => $validated['name'],
            'company_name' => $validated['company_name'],
            'email' => $validated['email'],
            'contact_consent' => true,
            'origin_page' => Dcx_Ssp_Constants::ORIGIN_PAGE,
            'source_url' => Dcx_Ssp_Constants::SOURCE_URL,
            'form_id' => Dcx_Ssp_Constants::FORM_ID,
            'form_name' => Dcx_Ssp_Constants::FORM_NAME,
            'project' => [
                'pronac_number' => Dcx_Ssp_Constants::PRONAC_NUMBER,
                'edition_year' => Dcx_Ssp_Constants::EDITION_YEAR,
            ],
            'sponsorship_simulation' => $sim,
        ];

        foreach (['role_title', 'whatsapp', 'city', 'state', 'segment', 'message'] as $opt) {
            if (array_key_exists($opt, $validated) && $validated[$opt] !== null && $validated[$opt] !== '') {
                $out[$opt] = $validated[$opt];
            }
        }

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utm) {
            if (array_key_exists($utm, $validated)) {
                $out[$utm] = $validated[$utm];
            }
        }

        return $out;
    }
}
