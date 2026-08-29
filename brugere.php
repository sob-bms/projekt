<?php
require __DIR__ . '/inc/bootstrap.php';
$bruger = kraev_rolle([ROLLE_ADMINISTRATOR]);

$liste = $pdo->query('SELECT * FROM brugere ORDER BY aktiv DESC, navn')->fetchAll();

require __DIR__ . '/inc/header.php';
?>
<div class="side-header">
    <h1>Interne brugere / BMS-ansvarlige</h1>
    <a class="knap" href="bruger-form.php">+ Ny bruger</a>
</div>

<table class="data-tabel">
    <thead>
        <tr><th>Initialer</th><th>Navn</th><th>E-mail</th><th>Rolle</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($liste as $b): ?>
        <tr>
            <td><?= e($b['initialer']) ?></td>
            <td><?= e($b['navn']) ?></td>
            <td><?= e($b['email']) ?></td>
            <td><?= e(rolle_label($b['rolle'])) ?></td>
            <td><?= $b['aktiv'] ? 'Aktiv' : 'Inaktiv' ?></td>
            <td class="handlinger">
                <a href="bruger-form.php?id=<?= (int)$b['id'] ?>">Rediger</a>
                <?php if ((int)$b['id'] !== (int)$bruger['id']): ?>
                <form method="post" action="bruger-slet.php" onsubmit="return confirm('Deaktiver <?= e(addslashes($b['navn'])) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button type="submit" class="link-knap"><?= $b['aktiv'] ? 'Deaktiver' : 'Aktivér' ?></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
