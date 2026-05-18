<?php
declare(strict_types=1);

/**
 * Front controller. All /api/* requests land here via .htaccess rewrite.
 */

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/routes/auth.php';
require __DIR__ . '/routes/public.php';
require __DIR__ . '/routes/claim.php';
require __DIR__ . '/routes/admin.php';

apply_cors((string)($CONFIG['cors_allow_origin'] ?? '*'));

// Path comes from the rewrite (`?path=...`) or from PATH_INFO / REQUEST_URI fallback.
$path = $_GET['path'] ?? '';
if ($path === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '';
    // Strip the leading /api or /api/ prefix when present.
    $uri = preg_replace('#^/api/?#', '', $uri) ?? '';
    $path = $uri;
}
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    // [method, regex, handler-callable, capture-names]
    ['GET',    '#^healthz$#',                                                'route_healthz'],
    ['GET',    '#^settings$#',                                               'route_public_settings'],

    ['POST',   '#^auth/request-otp$#',                                       'route_auth_request_otp'],
    ['POST',   '#^auth/verify-otp$#',                                        'route_auth_verify_otp'],
    ['POST',   '#^auth/register$#',                                          'route_auth_register'],
    ['POST',   '#^auth/login$#',                                             'route_auth_login'],
    ['POST',   '#^auth/forgot-password$#',                                   'route_auth_forgot_password'],
    ['POST',   '#^auth/reset-password$#',                                    'route_auth_reset_password'],
    ['POST',   '#^auth/change-password$#',                                   'route_auth_change_password'],
    ['GET',    '#^auth/me$#',                                                'route_auth_me'],
    ['PATCH',  '#^auth/profile$#',                                           'route_auth_profile_patch'],
    ['GET',    '#^auth/oauth/(?P<provider>[a-z]+)/start$#',                  'route_auth_oauth_start'],
    ['GET',    '#^auth/oauth/(?P<provider>[a-z]+)/callback$#',               'route_auth_oauth_callback'],

    ['GET',    '#^brands$#',                                                 'route_public_brands'],
    ['GET',    '#^slug-check$#',                                             'route_public_slug_check'],
    ['GET',    '#^institutions$#',                                           'route_public_institutions'],
    ['GET',    '#^institutions/stats$#',                                     'route_public_stats'],
    ['GET',    '#^i/(?P<brand>[^/]+)/(?P<slug>[^/]+)/notices$#',             'route_public_get_notices'],
    ['GET',    '#^i/(?P<brand>[^/]+)/(?P<slug>[^/]+)$#',                     'route_public_get_institution'],

    ['POST',   '#^claims$#',                                                 'route_claim_submit'],
    ['GET',    '#^claims/mine$#',                                            'route_claim_mine'],
    ['DELETE', '#^claims/(?P<id>\d+)$#',                                     'route_claim_withdraw'],
    ['POST',   '#^claims/(?P<id>\d+)/documents$#',                           'route_claim_upload_doc'],

    ['GET',    '#^tenant/(?P<id>\d+)/site$#',                                'route_tenant_site_get'],
    ['PATCH',  '#^tenant/(?P<id>\d+)/site$#',                                'route_tenant_site_patch'],
    ['POST',   '#^tenant/(?P<id>\d+)/upload-image$#',                        'route_tenant_upload_image'],
    ['GET',    '#^tenant/(?P<id>\d+)/notices$#',                             'route_tenant_notices_list'],
    ['POST',   '#^tenant/(?P<id>\d+)/notices$#',                             'route_tenant_notice_create'],
    ['DELETE', '#^tenant/(?P<id>\d+)/notices/(?P<nid>\d+)$#',                'route_tenant_notice_delete'],

    ['GET',    '#^admin/stats$#',                                            'route_admin_stats'],
    ['GET',    '#^admin/claims$#',                                           'route_admin_list_claims'],
    ['GET',    '#^admin/claims/(?P<id>\d+)$#',                               'route_admin_get_claim'],
    ['POST',   '#^admin/claims/(?P<id>\d+)/decide$#',                        'route_admin_decide'],
    ['POST',   '#^admin/claims/(?P<id>\d+)/dns-retry$#',                     'route_admin_dns_retry'],
    ['GET',    '#^admin/documents/(?P<id>\d+)$#',                            'route_admin_doc_download'],
    ['GET',    '#^admin/reserved-slugs$#',                                   'route_admin_reserved_list'],
    ['POST',   '#^admin/reserved-slugs$#',                                   'route_admin_reserved_add'],
    ['DELETE', '#^admin/reserved-slugs/(?P<id>\d+)$#',                       'route_admin_reserved_delete'],
    ['GET',    '#^admin/audit$#',                                            'route_admin_audit_log'],
    ['GET',    '#^admin/export/claims\.csv$#',                               'route_admin_export_claims_csv'],
    ['GET',    '#^admin/export/users\.csv$#',                                'route_admin_export_users_csv'],
    ['GET',    '#^admin/settings$#',                                         'route_admin_settings_get'],
    ['POST',   '#^admin/settings$#',                                         'route_admin_settings_set'],

    ['GET',    '#^sitemap\.xml$#',                                           'route_public_sitemap'],
];

function route_healthz(array $CONFIG): void
{
    send_json(['ok' => true, 'service' => 'free-subdomain-platform', 'demo_mode' => !empty($CONFIG['demo_mode'])]);
}

$matched = null;
$args = [];
foreach ($routes as [$m, $re, $fn]) {
    if ($m !== $method) continue;
    if (preg_match($re, $path, $m_)) {
        $matched = $fn;
        foreach ($m_ as $k => $v) {
            if (!is_int($k)) $args[$k] = $v;
        }
        break;
    }
}
if (!$matched) {
    send_error("No route: $method /$path", 404);
}

// Build positional args in a stable order.
$callArgs = [$CONFIG];
$nameOrder = ['provider','brand','slug','id','nid'];
foreach ($nameOrder as $key) {
    if (array_key_exists($key, $args)) {
        $callArgs[] = ctype_digit($args[$key]) ? (int)$args[$key] : $args[$key];
    }
}
try {
    call_user_func_array($matched, $callArgs);
} catch (Throwable $e) {
    error_log('Unhandled: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    send_error('Server error', 500);
}
