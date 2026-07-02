<?php



declare(strict_types=1);



namespace App\Controllers;



use App\Core\Controller;

use App\Models\ActivityLog;

use App\Models\CollectorApplication;

use App\Models\CollectorApplicationDocument;

use App\Models\Role;

use App\Models\User;
use App\Services\EmailEventService;

use Throwable;



/**

 * Jornada pública documental — /captadores/credenciamento/{token}

 */

final class CollectorPublicController extends Controller

{

    public function show(array $params): void

    {

        $token = trim((string) ($params['token'] ?? ''));

        $model = new CollectorApplication();

        $app   = $model->findByPublicToken($token);



        if ($app === null) {

            $this->renderError('Link inválido', 'Não encontramos este processo de credenciamento.');

            return;

        }



        if ($model->hasCompletedOnboarding($app)) {

            if ($this->isAuthenticated() && (int) ($_SESSION['user_id'] ?? 0) === (int) ($app['user_created_id'] ?? 0)) {

                $this->redirect('/dashboard');

                return;

            }



            $this->renderError(

                'Cadastro concluído',

                'Seu acesso já foi criado. Entre no sistema com seu e-mail e senha.'

            );

            return;

        }



        $check = $model->validatePublicToken($app);

        if (!$check['valid']) {

            $this->renderError('Link indisponível', (string) $check['reason']);

            return;

        }



        if ($this->isAuthenticated()) {

            $sessionEmail = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));

            $appEmail = strtolower(trim((string) ($app['email'] ?? '')));

            if ($sessionEmail !== '' && $sessionEmail === $appEmail) {

                $this->redirect('/dashboard');

                return;

            }

        }



        $docModel = new CollectorApplicationDocument();
        $appId = (int) $app['id'];
        $docProgress = $model->documentProgress($appId);

        $sigModel = new \App\Models\SignatureRequest();
        $signatureProgress = $model->signatureStageProgress($appId);
        $signatureStageItems = $signatureProgress['items'];

        $this->view('collector_applications/public/journey', [

            'title'                => 'Credenciamento de captador',

            'application'          => $app,

            'documents'            => $docModel->findByApplication($appId),

            'docProgress'          => $docProgress,

            'allDocumentsSubmitted'=> $model->allDocumentsSubmitted($appId),

            'docTypes'             => $docModel->getAllTypes(),

            'docStatuses'          => $docModel->getStatuses(),

            'statuses'             => $model->getStatuses(),

            'steps'                => $model->getJourneySteps(),

            'currentStep'          => $model->journeyStepKey($app),

            'entityTypeLabel'      => $model->entityTypeLabel($app),

            'isLegalEntity'        => $model->isLegalEntity($app),

            'showRegistrationForm' => $model->canSelfRegister($app),

            'signatureStageItems'  => $signatureStageItems,

            'signatureProgress'    => $signatureProgress,

        ], 'layouts/admin');

    }



    public function upload(array $params): void

    {

        $token = trim((string) ($params['token'] ?? ''));

        $model = new CollectorApplication();

        $app   = $model->findByPublicToken($token);



        if ($app === null) {

            $this->renderError('Link inválido', 'Não encontramos este processo de credenciamento.');

            return;

        }



        if ($model->hasCompletedOnboarding($app)) {

            flash('info', 'Cadastro já concluído. Faça login no sistema.');

            $this->redirect('/login');

            return;

        }



        $check = $model->validatePublicToken($app);

        if (!$check['valid']) {

            $this->renderError('Link indisponível', (string) $check['reason']);

            return;

        }



        if ($this->isUploadRateLimited($token)) {

            flash('error', 'Muitas tentativas de envio. Aguarde alguns minutos.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        $slotId = (int) input('document_slot_id', 0);

        $docModel = new CollectorApplicationDocument();

        $slot = $docModel->findById($slotId);

        if ($slot === null || (int) ($slot['collector_application_id'] ?? 0) !== (int) $app['id']) {

            flash('error', 'Documento inválido.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        if (!empty($app['archived_at']) || (string) ($app['status'] ?? '') === 'arquivado') {

            flash('error', 'Este processo foi arquivado e não aceita novos envios.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        if ($model->canSelfRegister($app)) {

            flash('error', 'A fase documental foi encerrada. Conclua o cadastro de acesso abaixo.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }

        $postDocumental = in_array((string) ($app['status'] ?? ''), [
            'aprovado',
            'aguardando_assinatura_contratual',
            'contrato_assinado',
            'acesso_preparado',
            'acesso_liberado',
            'reprovado',
        ], true);

        if ($postDocumental) {

            flash('error', 'A fase documental foi encerrada. Não é possível enviar novos documentos por este link.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        $file = $_FILES['document_file'] ?? [];

        $errors = $docModel->validateUpload(is_array($file) ? $file : []);

        if ($errors !== []) {

            flash('error', implode(' ', $errors));

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        try {

            $docModel->attachUpload($slotId, $file);

            $model->syncDocumentStatus((int) $app['id']);

            $this->registerUploadAttempt($token);

            (new ActivityLog())->record('collector_document_uploaded', null, 'collector_application_document', $slotId);

            $progress = $model->documentProgress((int) $app['id']);
            $updated = $model->findById((int) $app['id']) ?? $app;
            (new EmailEventService())->sendToCollector('collector_document_uploaded', $updated, [
                'public_url' => app_url('/captadores/credenciamento/' . rawurlencode($token)),
            ]);

            if ($model->allDocumentsSubmitted((int) $app['id'])) {
                (new EmailEventService())->sendToCollector('collector_documents_completed', $updated, [
                    'public_url' => app_url('/captadores/credenciamento/' . rawurlencode($token)),
                ]);
                (new EmailEventService())->sendToTeam('collector_documents_completed_internal', $updated);

                flash('success', 'Documento enviado. Pacote documental completo! A equipe iniciará a análise em breve.');

            } else {

                flash('success', sprintf(

                    'Documento enviado. Envie todos os itens pendentes (%d de %d concluídos).',

                    $progress['submitted'],

                    $progress['total']

                ));

            }

        } catch (Throwable $e) {

            error_log('[COLLECTOR UPLOAD] ' . $e->getMessage());

            flash('error', 'Não foi possível enviar o documento.');

        }



        $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

    }



    public function register(array $params): void

    {

        $token = trim((string) ($params['token'] ?? ''));

        $model = new CollectorApplication();

        $app   = $model->findByPublicToken($token);



        if ($app === null) {

            $this->renderError('Link inválido', 'Não encontramos este processo de credenciamento.');

            return;

        }



        if ($model->hasCompletedOnboarding($app)) {

            flash('info', 'Cadastro já concluído. Faça login no sistema.');

            $this->redirect('/login');

            return;

        }



        $check = $model->validatePublicToken($app);

        if (!$check['valid']) {

            $this->renderError('Link indisponível', (string) $check['reason']);

            return;

        }



        if (!$model->canSelfRegister($app)) {

            flash('error', 'O cadastro de acesso ainda não está liberado pela equipe.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        if ($this->isRegisterRateLimited($token)) {

            flash('error', 'Muitas tentativas. Aguarde alguns minutos.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        $name = trim((string) input('name', (string) ($app['name'] ?? '')));

        $email = strtolower(trim((string) input('email', (string) ($app['email'] ?? ''))));

        $password = (string) ($_POST['password'] ?? '');

        $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');



        $errors = validate(

            ['name' => $name, 'email' => $email, 'password' => $password],

            ['name' => 'required|min:3', 'email' => 'required|email', 'password' => 'required|min:8']

        );



        if ($email !== strtolower(trim((string) ($app['email'] ?? '')))) {

            $errors['email'] = 'Use o mesmo e-mail informado na manifestação.';

        }



        if ($password !== $passwordConfirm) {

            $errors['password_confirmation'] = 'As senhas não conferem.';

        }



        $userModel = new User();

        if ($userModel->emailExists($email)) {

            $errors['email'] = 'Já existe um usuário com este e-mail. Entre em contato com a equipe Dança Carajás.';

        }



        if ($errors !== []) {

            $this->registerRegisterAttempt($token);

            flash('error', implode(' ', array_values($errors)));

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        $roleRow = (new Role())->findBySlug('captador-externo');

        if ($roleRow === null) {

            flash('error', 'Perfil de captador não configurado. Entre em contato com a equipe.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

            return;

        }



        try {

            $userId = (int) $userModel->createUser(
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                'active',
                false
            );
            $userModel->syncRoles($userId, [(int) $roleRow['id']]);



            $appId = (int) $app['id'];

            $model->update($appId, [

                'user_created_id' => $userId,

                'access_status'   => 'acesso_liberado',

                'status'          => 'acesso_liberado',

                'updated_at'      => date('Y-m-d H:i:s'),

            ]);

            $model->revokePublicToken($appId);



            (new ActivityLog())->record('collector_access_self_registered', $userId, 'collector_application', $appId);

            (new ActivityLog())->record('collector_public_token_revoked', $userId, 'collector_application', $appId);
            (new EmailEventService())->sendToCollector('collector_access_self_registered', $model->findById($appId) ?? $app);
            (new EmailEventService())->sendToTeam('collector_access_self_registered_internal', $model->findById($appId) ?? $app);



            $user = $userModel->find($userId);

            if ($user !== null) {

                $this->establishSession($user);

            }



            flash('success', 'Cadastro concluído! Bem-vindo ao Dança Carajás Captação.');

            $this->redirect('/dashboard');

        } catch (Throwable $e) {

            error_log('[COLLECTOR REGISTER] ' . $e->getMessage());

            $this->registerRegisterAttempt($token);

            flash('error', 'Não foi possível concluir o cadastro. Tente novamente.');

            $this->redirect('/captadores/credenciamento/' . rawurlencode($token));

        }

    }



    /** @param array<string, mixed> $user */

    private function establishSession(array $user): void

    {

        session_regenerate_id(true);



        $_SESSION['user_id']    = (int) $user['id'];

        $_SESSION['user_name']  = (string) $user['name'];

        $_SESSION['user_email'] = (string) $user['email'];

        $_SESSION['_created_at'] = time();

        $_SESSION['_last_activity'] = time();



        try {

            $userModel = new User();

            $roles = $userModel->rolesFor((int) $user['id']);

            $_SESSION['roles'] = array_map(static fn ($r): string => (string) $r['slug'], $roles);

            $_SESSION['role_names'] = array_map(static fn ($r): string => (string) $r['name'], $roles);

            $_SESSION['permissions'] = $userModel->permissionsFor((int) $user['id']);

            $userModel->registerSuccessfulLogin((int) $user['id']);

            (new ActivityLog())->record('login_success', (int) $user['id'], 'user', (int) $user['id']);

        } catch (Throwable $e) {

            error_log('[COLLECTOR REGISTER] Sessão: ' . $e->getMessage());

            $_SESSION['roles'] = [];

            $_SESSION['role_names'] = [];

            $_SESSION['permissions'] = [];

        }

    }



    private function renderError(string $title, string $message): void

    {

        $this->view('collector_applications/public/error', [

            'title'   => $title,

            'message' => $message,

        ], 'layouts/admin');

    }



    private function isUploadRateLimited(string $token): bool

    {

        return $this->isRateLimited('collector_upload_' . md5($token), 10);

    }



    private function isRegisterRateLimited(string $token): bool

    {

        return $this->isRateLimited('collector_register_' . md5($token), 5);

    }



    private function isRateLimited(string $keySuffix, int $maxAttempts): bool

    {

        $file = dirname(__DIR__, 2) . '/storage/ratelimit/' . $keySuffix . '.json';

        if (!is_file($file)) {

            return false;

        }

        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data)) {

            return false;

        }

        $recent = array_filter((array) ($data['attempts'] ?? []), static fn ($ts) => (time() - (int) $ts) < 600);



        return count($recent) >= $maxAttempts;

    }



    private function registerUploadAttempt(string $token): void

    {

        $this->registerRateAttempt('collector_upload_' . md5($token));

    }



    private function registerRegisterAttempt(string $token): void

    {

        $this->registerRateAttempt('collector_register_' . md5($token));

    }



    private function registerRateAttempt(string $keySuffix): void

    {

        $dir = dirname(__DIR__, 2) . '/storage/ratelimit';

        if (!is_dir($dir)) {

            @mkdir($dir, 0755, true);

        }

        $file = $dir . '/' . $keySuffix . '.json';

        $data = ['attempts' => []];

        if (is_file($file)) {

            $decoded = json_decode((string) file_get_contents($file), true);

            if (is_array($decoded)) {

                $data = $decoded;

            }

        }

        $data['attempts'][] = time();

        file_put_contents($file, json_encode($data));

    }

    /** @param array<string, mixed>|null $activeSignature */
    private function signaturePdfUrlForApplication(?array $activeSignature): ?string
    {
        if ($activeSignature === null || (string) ($activeSignature['status'] ?? '') !== 'assinado') {
            return null;
        }

        $sigModel = new \App\Models\SignatureRequest();
        $signers = $sigModel->signersForRequest((int) ($activeSignature['id'] ?? 0));
        foreach ($signers as $signer) {
            if ((string) ($signer['status'] ?? '') === 'assinado' && !empty($signer['public_token'])) {
                return app_url('/assinatura/' . rawurlencode((string) $signer['public_token']) . '/pdf');
            }
        }

        return null;
    }

}


