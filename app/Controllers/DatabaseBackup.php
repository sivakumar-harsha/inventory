<?php

namespace App\Controllers;

use App\Libraries\DatabaseBackupManager;
use App\Models\DatabaseBackupModel;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Release 4.8.8C: Backup & Restore Center controller. Pure orchestration —
 * every mysqldump/mysql shell-out, escapeshellarg() call and
 * database_backups write lives in App\Libraries\DatabaseBackupManager, not
 * here. This controller only: reads the request, calls the library, and
 * turns the result into a view/redirect/JSON/download response.
 *
 * No role/permission system exists anywhere in this codebase (see
 * app/Filters/AuthFilter.php — a single "logged in or not" gate, same as
 * every other module). Every action below is reachable by any logged-in
 * user; there is no "admin-only" restriction on backup/restore/delete. See
 * Known Limitations in the Release 4.8.8C report.
 */
class DatabaseBackup extends Controller
{
    /** GET database-backup — dashboard: KPI cards + backup history table. */
    public function index()
    {
        $manager = new DatabaseBackupManager();
        $rows    = $manager->listBackups();

        // Phase D: "file missing" is computed here (controller), not in the view.
        foreach ($rows as &$row) {
            $row['file_missing'] = empty($row['file_name']) ? null : ! is_file($manager->backupPath($row['file_name']));
        }
        unset($row);

        $totalBackups = 0;
        $totalSize    = 0;
        $latestBackup = null;
        $lastRestore  = null;

        // $rows is already DESC by created_at, so the first match of each
        // kind found while scanning is the latest one.
        foreach ($rows as $row) {
            if ($row['backup_type'] === 'RESTORE') {
                if ($lastRestore === null) {
                    $lastRestore = $row;
                }
                continue;
            }

            $totalBackups++;
            $totalSize += (int) $row['file_size'];
            if ($latestBackup === null) {
                $latestBackup = $row;
            }
        }

        return view('database_backup/index', [
            'title'         => 'Backup & Restore Center',
            'rows'          => $rows,
            'totalBackups'  => $totalBackups,
            'totalSize'     => $totalSize,
            'totalSizeText' => $manager->humanFileSize($totalSize),
            'latestBackup'  => $latestBackup,
            'lastRestore'   => $lastRestore,
        ]);
    }

    /** POST database-backup/create — manual backup, redirects back with a flash message. */
    public function create()
    {
        $manager = new DatabaseBackupManager();
        $result  = $manager->createBackup('MANUAL', session()->get('user_id'), null);

        if ($result['success']) {
            return redirect()->to('database-backup')->with('success', 'Backup created successfully: ' . $result['file_name'] . ' (' . $manager->humanFileSize((int) $result['file_size']) . ').');
        }

        return redirect()->to('database-backup')->with('error', 'Backup failed: ' . $result['error']);
    }

    /** GET database-backup/download/{id} — streams the .sql file as an attachment. */
    public function download($id)
    {
        $manager = new DatabaseBackupManager();
        $info    = $manager->downloadBackup((int) $id);

        if ($info === null) {
            throw PageNotFoundException::forPageNotFound('Backup record or file not found.');
        }

        return $this->response->download($info['path'], null);
    }

    /** POST database-backup/delete/{id} — deletes the history row and, if present, the file. JSON response (matches the Expenses delete-modal AJAX convention). */
    public function deleteRecord($id)
    {
        $model = new DatabaseBackupModel();
        $row   = $model->find((int) $id);

        if (! $row) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Backup record not found.']])->setStatusCode(404);
        }

        $manager = new DatabaseBackupManager();
        $ok      = $manager->deleteBackup((int) $id);

        if (! $ok) {
            return $this->response->setJSON(['status' => false, 'errors' => ['Failed to delete the backup record.']])->setStatusCode(500);
        }

        return $this->response->setJSON(['status' => true]);
    }

    /** GET database-backup/restore — renders the upload form. */
    public function restoreForm()
    {
        return view('database_backup/restore', [
            'title'                => 'Restore Database',
            'maxUploadSizeText'    => (new DatabaseBackupManager())->humanFileSize((int) config('Backup')->maxUploadSizeBytes),
            'iniUploadMaxFilesize' => ini_get('upload_max_filesize'),
            'iniPostMaxSize'       => ini_get('post_max_size'),
        ]);
    }

    /** POST database-backup/restore — validates the upload, runs safety-backup-then-restore, redirects with a result banner. */
    public function restore()
    {
        $confirmed = (bool) $this->request->getPost('confirm_overwrite');

        if (! $confirmed) {
            return redirect()->to('database-backup/restore')->with('error', 'You must tick "I understand this will overwrite existing data" before restoring.');
        }

        $file = $this->request->getFile('sql_file');

        if ($file === null || ! $file->isValid()) {
            $reason = $file !== null ? $file->getErrorString() : 'No file was uploaded.';
            return redirect()->to('database-backup/restore')->with('error', 'Upload rejected: ' . $reason);
        }

        $manager    = new DatabaseBackupManager();
        $validation = $manager->validateBackup($file);

        if (! $validation['valid']) {
            return redirect()->to('database-backup/restore')->with('error', 'Upload rejected: ' . $validation['reason']);
        }

        // Safety Rule #2(b): never trust the client-supplied original
        // filename for the path we actually import from — generate our own
        // server-side name in the writable/uploads holding area.
        $destName = 'AANDA_Restore_Upload_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
        $destDir  = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

        if (! is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        try {
            $file->move($destDir, $destName);
        } catch (\Throwable $e) {
            log_message('error', 'DatabaseBackup::restore() failed to move uploaded file: ' . $e->getMessage());
            return redirect()->to('database-backup/restore')->with('error', 'Could not save the uploaded file on the server: ' . $e->getMessage());
        }

        $destPath = $destDir . $destName;
        $result   = $manager->restoreBackup($destPath, session()->get('user_id'));

        if (is_file($destPath)) {
            @unlink($destPath);
        }

        if ($result['success']) {
            $safetyLabel = $result['safety_backup_id'] ? ' (#' . $result['safety_backup_id'] . ')' : '';
            return redirect()->to('database-backup')->with('success', 'Database restored successfully from the uploaded backup. A safety backup' . $safetyLabel . ' was taken automatically before the restore and is listed in the history below.');
        }

        $safetyNote = $result['safety_backup_id']
            ? ' The pre-restore safety backup (#' . $result['safety_backup_id'] . ') is available in the history below for a manual re-import.'
            : '';

        return redirect()->to('database-backup')->with('error', 'Restore failed: ' . $result['error'] . $safetyNote);
    }
}
