<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Core/App.php';
(new App\Core\App(BASE_PATH))->boot();

$jar = tempnam(sys_get_temp_dir(), 'doc');
$req = function (string $path, array $post = [], ?CURLFile $file = null) use ($jar): array {
    $ch = curl_init('http://localhost' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($post !== []) {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($file !== null) {
            $post['document_file'] = $file;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
    }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    preg_match('/Location:\s(\S+)/i', $raw, $m);
    return ['code' => $code, 'location' => $m[1] ?? null, 'body' => $raw];
};

$html = $req('/login')['body'];
preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
$req('/login', ['_csrf' => $m[1], 'email' => 'admin@dancacarajas.com', 'password' => 'Mudar@123']);

$sponsorId = (int) ($argv[1] ?? 11);
$html = $req('/documents/create?sponsor_id=' . $sponsorId)['body'];
preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);

$tmp = sys_get_temp_dir() . '/doc_test12.txt';
file_put_contents($tmp, 'Documento teste upload etapa 12');
$file = new CURLFile($tmp, 'text/plain', 'test.txt');

$res = $req('/documents', [
    '_csrf' => $m[1],
    'sponsor_id' => (string) $sponsorId,
    'company_id' => '17',
    'title' => 'DOCUMENTO TESTE — PATROCINADOR',
    'category' => 'documento_comercial',
    'status' => 'ativo',
    'access_level' => 'interno',
    'document_date' => date('Y-m-d'),
], $file);

echo 'Upload HTTP ' . $res['code'] . ' → ' . ($res['location'] ?? 'no redirect') . PHP_EOL;

if ($res['location'] && preg_match('#/documents/(\d+)#', $res['location'], $dm)) {
    $docId = (int) $dm[1];
    $show = $req('/documents/' . $docId);
    echo 'Show HTTP ' . $show['code'] . PHP_EOL;
    echo (str_contains($show['body'], '/sponsors/' . $sponsorId) ? 'PASS' : 'FAIL') . ' link patrocinador' . PHP_EOL;
    $dl = $req('/documents/' . $docId . '/download');
    echo 'Download HTTP ' . $dl['code'] . PHP_EOL;
}
