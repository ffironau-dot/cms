<?php
/**
 * Раздел «Файлы» (п. 6.1): загрузка картинок для вставки в HTML-код
 * больших текстов, список с миниатюрами и адресами, поиск, удаление.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\FileItem;
use Core\Paginator;
use Core\Request;
use Core\Db;

final class FilesController extends Controller
{
    public function index(): void
    {
        if ($this->isValidPost()) {
            $uploaded = 0;
            $files = $_FILES['files'] ?? null;
            if (is_array($files) && is_array($files['name'] ?? null)) {
                foreach (array_keys($files['name']) as $i) {
                    $one = [
                        'name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i], 'size' => $files['size'][$i],
                    ];
                    if ($one['error'] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    try {
                        if ($one['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($one['tmp_name'])) {
                            throw new \RuntimeException('«' . $one['name'] . '»: ошибка загрузки (код ' . $one['error'] . ').');
                        }
                        $name = FileItem::upload($one);
                        $uploaded++;
                        $this->flash('success', 'Загружен файл: ' . $name);
                    } catch (\RuntimeException $e) {
                        $this->flash('error', $e->getMessage());
                    }
                }
            }
            if ($uploaded === 0 && empty($_SESSION['flash'])) {
                $this->flash('error', 'Выберите файл для загрузки.');
            }
            $this->redirect('/files');
        }
        $q = trim(Request::get('q'));
        $page = max(1, Request::getInt('page'));
        $total = FileItem::search($q, 1, 0)['total'];
        $pager = new Paginator($total, FileItem::PER_PAGE, $page);
        if (!$pager->isValid()) {
            $pager = new Paginator($total, FileItem::PER_PAGE, 1);
        }
        $this->render('files', [
            'rows'  => FileItem::search($q, FileItem::PER_PAGE, $pager->offset())['rows'],
            'pager' => $pager,
            'q'     => $q,
        ], 'Файлы', 'files');
    }

    public function delete(): void
    {
        $id = Request::getInt('id');
        $row = Db::row('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$row) {
            $this->flash('error', 'Файл не найден.');
            $this->redirect('/files');
        }
        if ($this->isValidPost()) {
            FileItem::delete($id);
            $this->flash('success', 'Файл «' . $row['filename'] . '» удалён.');
            $this->redirect('/files');
        }
        $this->confirm('Удаление файла', 'Удалить файл «' . $row['filename'] . '»? Использование файла в текстах не проверяется.', '/files', 'files',
            ['image' => \Core\Upload::url(FileItem::UPLOAD_DIR, $row['filename'])]);
    }
}
