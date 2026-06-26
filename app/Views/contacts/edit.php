<?php
/**
 * Edição de contato.
 */
$contact     = $contact ?? [];
$cid         = (int) ($contact['id'] ?? 0);
$formAction  = app_url('/contacts/' . $cid . '/update');
$submitLabel = 'Salvar alterações';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Contatos</span>
                <h1 class="h2-section">Editar contato</h1>
                <p class="page-sub"><?= e($contact['name'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/contacts/' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
