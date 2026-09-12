<?php
declare(strict_types=1);

final class Dcx_Ssp_Secrets
{
    public static function resolveToken(): ?string
    {
        if (defined('DCX_CRM_LEAD_ENDPOINT_SECRET')) {
            $v = trim((string) constant('DCX_CRM_LEAD_ENDPOINT_SECRET'));
            if ($v !== '') {
                return $v;
            }
        }

        $env = getenv('DCX_CRM_LEAD_ENDPOINT_SECRET');
        if (is_string($env)) {
            $v = trim($env);
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }
}
