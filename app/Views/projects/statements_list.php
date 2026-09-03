<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="page-title">
    <span><i class="bi bi-file-earmark-text me-2"></i>Project Statements</span>
</div>

<div class="card-custom">
    <div class="card-custom-body">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Project Name</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th style="text-align:center">Statement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No projects found</td></tr>
                    <?php else: ?>
                    <?php foreach ($projects as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= esc($p['name']) ?></strong></td>
                        <td><?= esc($p['customer_name']) ?></td>
                        <td>
                            <span class="badge-status badge-<?= strtolower($p['status']) ?>">
                                <?= str_replace('_', ' ', $p['status']) ?>
                            </span>
                        </td>
                        <td style="text-align:center">
                            <a href="<?= base_url('projects/statement/' . $p['id']) ?>" class="btn-view">
                                <i class="bi bi-file-earmark-text"></i> View Statement
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
