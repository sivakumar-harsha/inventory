<?php
/**
 * Release 4.8.5A: the field rows of a manual bank entry form, driven by the
 * controller's $cfg['fields']. Shared by bank_entries/form.php (create/edit,
 * $idPrefix "f_") and the unified Transactions entry screen (one prefix per
 * voucher type). Pulled in with a plain PHP include so it shares the caller's
 * scope; expects $cfg, $entry (null on create), $accounts, $accountMap, $isEdit
 * and $idPrefix.
 */
?>
                <?php foreach ($cfg['fields'] as $field): ?>
                <?php
                    $name  = $field['name'];
                    $value = $entry[$name] ?? ($field['default'] ?? '');
                    $col   = ! empty($field['wide']) ? 'col-12' : 'col-md-6';
                    $req   = ! empty($field['required']);
                    $id    = $idPrefix . $name;
                ?>
                <div class="<?= $col ?>">
                    <label class="form-label" for="<?= $id ?>"><?= esc($field['label']) ?><?= $req ? ' *' : '' ?></label>

                    <?php if ($field['type'] === 'date'): ?>
                        <input type="date" name="<?= $name ?>" id="<?= $id ?>" class="form-control" value="<?= esc($value, 'attr') ?>"<?= $req ? ' required' : '' ?>>

                    <?php elseif ($field['type'] === 'bank'): ?>
                        <select name="<?= $name ?>" id="<?= $id ?>" class="form-control"<?= $req ? ' required' : '' ?>>
                            <option value="">Select bank account</option>
                            <?php foreach ($accounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>"<?= (string) $value === (string) $account['id'] ? ' selected' : '' ?>><?= esc($account['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isEdit && $value !== '' && ! isset($accountMap[(int) $value])): ?>
                        <div class="form-text text-danger">The account originally used is no longer active — choose an active account.</div>
                        <?php endif; ?>

                    <?php elseif ($field['type'] === 'money'): ?>
                        <input type="number" step="0.01" min="0.01" name="<?= $name ?>" id="<?= $id ?>" class="form-control" value="<?= $value !== '' ? esc(number_format((float) $value, 2, '.', ''), 'attr') : '' ?>"<?= $req ? ' required' : '' ?>>

                    <?php elseif ($field['type'] === 'select'): ?>
                        <select name="<?= $name ?>" id="<?= $id ?>" class="form-control"<?= $req ? ' required' : '' ?>>
                            <?php foreach ($field['options'] as $optValue => $optLabel): ?>
                            <option value="<?= esc($optValue, 'attr') ?>"<?= (string) $value === (string) $optValue ? ' selected' : '' ?>><?= esc($optLabel) ?></option>
                            <?php endforeach; ?>
                        </select>

                    <?php elseif ($field['type'] === 'textarea'): ?>
                        <textarea name="<?= $name ?>" id="<?= $id ?>" class="form-control" rows="2"<?= $req ? ' required' : '' ?>><?= esc($value) ?></textarea>

                    <?php else: ?>
                        <input type="text" name="<?= $name ?>" id="<?= $id ?>" class="form-control" value="<?= esc($value, 'attr') ?>"<?= isset($field['maxlength']) ? ' maxlength="' . (int) $field['maxlength'] . '"' : '' ?><?= isset($field['placeholder']) ? ' placeholder="' . esc($field['placeholder'], 'attr') . '"' : '' ?><?= $req ? ' required' : '' ?>>
                    <?php endif; ?>

                    <?php if (! empty($field['hint'])): ?>
                    <div class="form-text"><?= esc($field['hint']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
