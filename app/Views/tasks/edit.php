<?php
/**
 * Edição de tarefa.
 */
$tid         = (int) ($task['id'] ?? ($old['id'] ?? 0));
$formAction  = app_url('/tasks/' . $tid . '/update');
$submitLabel = 'Salvar alterações';
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Tarefas · Follow-ups</span>
                <h1 class="h2-section">Editar tarefa</h1>
                <p class="page-sub"><?= e($task['title'] ?? '') ?></p>
            </div>
            <a href="<?= e(app_url('/tasks/' . $tid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>
    </div>
</section>
