<?php
/**
 * Cadastro de tarefa.
 */
$formAction  = app_url('/tasks');
$submitLabel = 'Cadastrar tarefa';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Tarefas · Follow-ups</span>
                <h1 class="h2-section">Nova tarefa</h1>
                <p class="page-sub">Crie uma ação para não perder prazos da captação.</p>
            </div>
            <a href="<?= e(app_url('/tasks')) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
