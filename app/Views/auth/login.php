<?php
/**
 * Tela de login.
 *
 * Variaveis esperadas:
 * - $error         (string|null)
 * - $email         (string)
 * - $timeoutNotice (bool)
 */
$error         = $error ?? null;
$email         = $email ?? '';
$timeoutNotice = $timeoutNotice ?? false;
?>

<section class="form-box auth-box">
    <span class="kicker kicker-dark">Acesso restrito</span>

    <h1 class="h3-card auth-title">Dança Carajás Captação</h1>

    <p class="auth-text text-muted-dcx">
        Entre para acessar o ambiente administrativo de captação de patrocínio
        do Dança Carajás Festival 2026.
    </p>

    <?php if ($timeoutNotice): ?>
        <div class="alert alert-info">
            <i data-lucide="clock"></i>
            <span>Sua sessão expirou por inatividade. Entre novamente.</span>
        </div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="alert alert-error" role="alert">
            <i data-lucide="alert-triangle"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(app_url('/login')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="stack-sm" style="margin-bottom:16px;">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" autocomplete="username"
                   value="<?= e($email) ?>" required autofocus>
        </div>

        <div class="stack-sm" style="margin-bottom:22px;">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-yellow" style="width:100%;">
            <i data-lucide="log-in"></i> Entrar no sistema
        </button>
    </form>

    <div class="auth-links">
        <a href="<?= e(app_url('/forgot-password')) ?>">Esqueci minha senha</a>
    </div>

    <p class="auth-note">
        <i data-lucide="lock"></i>
        Acesso permitido apenas a usuários autorizados.
    </p>
</section>
