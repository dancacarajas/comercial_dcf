<?php
declare(strict_types=1);

final class Dcx_Ssp_Rest_Controller
{
    public static function register(): void
    {
        register_rest_route('dcx-crm/v1', '/sponsorship-simulation', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle($request)
    {
        $raw = $request->get_body();
        $lenHeader = $request->get_header('content-length');
        $contentLength = is_numeric($lenHeader) ? (int) $lenHeader : null;

        $result = Dcx_Ssp_Pipeline::handle($raw, $_SERVER, null, null, null, $contentLength);

        $response = new WP_REST_Response($result['body'], $result['http']);
        $response->header('Content-Type', 'application/json; charset=UTF-8');

        return $response;
    }
}
