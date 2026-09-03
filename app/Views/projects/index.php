<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
	
	.dataTables_wrapper .dataTables_paginate {
		margin-top: 10px;
		text-align: right;
	}

	.dataTables_wrapper .dataTables_paginate .paginate_button {
		background: #f1f5f9 !important;
		border: 1px solid #e2e8f0 !important;
		color: #334155 !important;
		padding: 4px 10px !important;
		margin: 2px !important;
		border-radius: 6px !important;
		font-size: 12px !important;
	}

	.dataTables_wrapper .dataTables_paginate .paginate_button.current {
		background: #2F7E8A !important;
		color: #fff !important;
		border: none !important;
	}

	.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
		background: #1e293b !important;
		color: #fff !important;
	}

	.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
		opacity: 0.5;
		cursor: not-allowed;
	}

	.dataTables_wrapper .dataTables_info {
		font-size: 12px;
		color: #64748b;
	}
	
	.dataTables_wrapper .dataTables_paginate {
		  float: right;
		  text-align: right;
		  padding-top: .25em;
		  padding-bottom: 7px;
		  padding-right: 10px;
		}

	.custom-search-box {
		max-width: 300px;
	}

	.custom-search-box input {
		border-radius: 8px;
		border: 1px solid #e2e8f0;
		padding: 6px 12px;
		font-size: 13px;
		transition: 0.2s;
	}

	.custom-search-box input:focus {
		border-color: #2F7E8A;
		box-shadow: 0 0 0 2px rgba(47,126,138,0.15);
	}
	.search-icon {
		position: absolute;
		top: 8px;
		left: 9px;
		color: #94a3b8;
		font-size: 12px;
	}
	
	.dataTables_wrapper .paginate_button i {
		font-size: 12px;
		vertical-align: middle;
	}

	.status-dropdown {
		display: inline-block;
	}

	.status-toggle {
		display: inline-flex;
		align-items: center;
		gap: 5px;
		padding: 3px 10px;
		font-size: 0.7rem;
		font-weight: 550;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		border-radius: 20px;
		border: 1px solid transparent;
		cursor: pointer;
		transition: filter 0.15s ease, box-shadow 0.15s ease;
	}

	.status-toggle:hover {
		filter: brightness(0.96);
	}

	.status-toggle:focus {
		outline: none;
		box-shadow: 0 0 0 3px rgba(47,126,138,0.18);
	}

	.status-toggle::after {
		display: none;
	}

	.status-toggle .status-caret {
		font-size: 9px;
		opacity: 0.75;
		transition: transform 0.15s ease;
	}

	.status-toggle[aria-expanded="true"] .status-caret {
		transform: rotate(180deg);
	}

	.status-menu {
		min-width: 140px;
		padding: 6px;
		border-radius: 10px;
		border: 1px solid #e2e8f0;
		box-shadow: 0 10px 24px rgba(15,23,42,0.12);
	}

	.status-menu .status-option {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 6px 10px;
		border-radius: 7px;
		font-size: 12.5px;
		font-weight: 500;
		color: #334155;
	}

	.status-menu .status-option:hover {
		background: #f1f5f9;
		color: #1e293b;
	}

	.status-menu .status-option .status-dot {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		flex: none;
	}

	.status-menu .status-option.active-option {
		background: #eef6f7;
		font-weight: 700;
	}

	.status-dot.dot-active    { background: #1d4ed8; }
	.status-dot.dot-on_hold   { background: #374151; }
	.status-dot.dot-completed { background: #15803d; }

</style>


<div class="page-title">
    <span><i class="bi bi-kanban me-2"></i>Projects</span>
	<div class="custom-search-box mb-1 position-relative">
		<i class="bi bi-search search-icon"></i>
		<input type="text" id="customSearch" class="form-control ps-4" placeholder="Search project...">
	</div>
    <a href="<?= base_url('projects/create') ?>" class="btn-save"><i class="bi bi-plus-lg"></i> Add Project</a>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" style="border-bottom:2px solid #e2e8f0">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'active' ? 'active' : '' ?>"
           href="<?= base_url('projects?tab=active') ?>"
           style="font-weight:600;color:<?= $tab === 'active' ? '#1e40af' : '#64748b' ?>">
            <i class="bi bi-activity me-1"></i> Active Projects
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'completed' ? 'active' : '' ?>"
           href="<?= base_url('projects?tab=completed') ?>"
           style="font-weight:600;color:<?= $tab === 'completed' ? '#1e40af' : '#64748b' ?>">
            <i class="bi bi-check2-circle me-1"></i> Completed Projects
        </a>
    </li>
</ul>

<div class="card-custom">
    <div class="table-responsive">
        <table id="projectTable" class="table-custom">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Project Name</th>
                    <th>Customer</th>
                    <th style="text-align:right">Contract Value</th>
                    <th style="text-align:right">Remaining Balance</th>
                    <th>Work Status</th>
                    <th>Billing Status</th>
                    <th style="text-align:center; width: 132.75px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= esc($p['name']) ?></strong></td>
                    <td><?= esc($p['customer_name']) ?></td>
                    <td style="text-align:right"><?= number_format($p['contract_value'], 2) ?></td>
                    <td class="text-end fw-bold text-primary">
                        &#8377;<?= number_format((float) $p['remaining_balance'], 2) ?>
                    </td>
                    <td>
                        <?php if ($tab === 'completed'): ?>
                        <span class="badge-status badge-<?= strtolower($p['status']) ?>">
                            <?= str_replace('_',' ', $p['status']) ?>
                        </span>
                        <?php else: ?>
                        <?php $statusLabels = ['ACTIVE' => 'Active', 'ON_HOLD' => 'On Hold', 'COMPLETED' => 'Completed']; ?>
                        <div class="dropdown status-dropdown">
                            <button type="button"
                                    class="status-toggle badge-status badge-<?= strtolower($p['status']) ?> dropdown-toggle"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    data-project-id="<?= $p['id'] ?>"
                                    data-current="<?= strtolower($p['status']) ?>">
                                <?= $statusLabels[$p['status']] ?? $p['status'] ?>
                                <i class="bi bi-chevron-down status-caret"></i>
                            </button>
                            <ul class="dropdown-menu status-menu">
                                <?php foreach ($statusLabels as $val => $label): ?>
                                <li>
                                    <a href="#" class="status-option <?= $p['status'] === $val ? 'active-option' : '' ?>" data-status="<?= $val ?>">
                                        <span class="status-dot dot-<?= strtolower($val) ?>"></span> <?= $label ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        // Release 2.3A (final): this column shows Billing Completion
                        // Status only (ACTIVE/PARTIAL/COMPLETED) — a manual, project-
                        // level flag, independent of the Outstanding Collection
                        // column which shows invoice collection money.
                        $bcs    = $p['billing_completion_status'] ?? 'ACTIVE';
                        $bcsMap = ['ACTIVE' => 'active', 'PARTIAL' => 'partial', 'COMPLETED' => 'completed'];
                        $bcsCls = $bcsMap[$bcs] ?? 'active';
                        ?>
                        <span class="badge-status badge-<?= $bcsCls ?>"><?= $bcs ?></span>
                    </td>
                    <td>
                        <a href="<?= base_url('projects/view/' . $p['id']) ?>" class="btn-view">
                            <i class="bi bi-eye"></i> 
                        </a>
                        <a href="<?= base_url('projects/edit/' . $p['id']) ?>" class="btn-edit">
                            <i class="bi bi-pencil"></i> 
                        </a>
                        <a href="<?= base_url('projects/delete/' . $p['id']) ?>" class="btn-delete"
                           onclick="return confirm('Delete this project?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
		$(document).ready(function () {
			var table = $('#projectTable').DataTable({
				paging: true,        // ✅ pagination
				searching: true,    // ❌ remove search box
				lengthChange: false, // ❌ remove "show entries"
				info: false,          // (optional) showing "1 to 10 of X"
				ordering: true,      // (optional sorting)
				pageLength: 10,      // default rows per page

				dom: 'tp' ,// ✅ ONLY table + pagination + info
				
				language: {
					paginate: {
						previous: '<i class="bi bi-chevron-left"></i>',
						next: '<i class="bi bi-chevron-right"></i>'
					},
					emptyTable: '<?= $tab === 'completed' ? 'No completed projects found.' : 'No active projects found.' ?>'
				}
			});

			 $('#customSearch').on('keyup', function () {
				table.search(this.value).draw();
			});

			var statusLabels = { ACTIVE: 'Active', ON_HOLD: 'On Hold', COMPLETED: 'Completed' };

			$('#projectTable').on('click', '.status-option', function (e) {
				e.preventDefault();

				var $option    = $(this);
				var $menu      = $option.closest('.status-menu');
				var $toggle    = $menu.siblings('.status-toggle');
				var projectId  = $toggle.data('project-id');
				var newStatus  = $option.data('status');
				var prevStatus = $toggle.data('current');

				if (newStatus === prevStatus.toUpperCase()) {
					return;
				}

				if (newStatus === 'COMPLETED' && !confirm('Mark this project as Completed? It will move to the Completed tab.')) {
					return;
				}

				$.ajax({
					url: '<?= base_url('projects/update-status/') ?>' + projectId,
					method: 'POST',
					data: { status: newStatus },
					dataType: 'json'
				}).done(function (res) {
					if (res && res.success) {
						$toggle
							.removeClass('badge-' + prevStatus)
							.addClass('badge-' + newStatus.toLowerCase())
							.data('current', newStatus.toLowerCase())
							.contents().first()[0].textContent = statusLabels[newStatus] + ' ';

						$menu.find('.status-option').removeClass('active-option');
						$option.addClass('active-option');

						if (newStatus === 'COMPLETED') {
							table.row($toggle.closest('tr')).remove().draw();
						}
					} else {
						alert((res && res.message) || 'Failed to update status.');
					}
				}).fail(function () {
					alert('Failed to update status. Please try again.');
				});
			});
		});
	</script>
<?= $this->endSection() ?>
