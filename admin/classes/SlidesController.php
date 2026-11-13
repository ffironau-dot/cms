<?php
/**
 * Слайдер на главной (п. 4.5): слайды и настройки автопрокрутки.
 */

declare(strict_types=1);

namespace Admin;

use Core\Models\Slide;
use Core\Request;
use Core\Settings;
use Core\Upload;

final class SlidesController extends Controller
{
    public function index(): void
    {
        $this->render('slides-list', [
            'slides'   => Slide::all(),
            'autoplay' => Settings::get('slider_autoplay') === '1',
            'interval' => Settings::get('slider_interval'),
        ], 'Слайдер на главной', 'slides');
    }

    /** Сохранение настроек слайдера. */
    public function settings(): void
    {
        if ($this->isValidPost()) {
            $interval = (int) Request::post('slider_interval');
            if ($interval < 1 || $interval > 600) {
                $this->flash('error', 'Интервал автопрокрутки — целое число секунд от 1 до 600.');
            } else {
                Settings::set('slider_autoplay', Request::post('slider_autoplay') === '1' ? '1' : '0');
                Settings::set('slider_interval', (string) $interval);
                $this->flash('success', 'Настройки слайдера сохранены.');
            }
        }
        $this->redirect('/slides');
    }

    public function edit(): void
    {
        $id = Request::getInt('id') ?: null;
        $slide = $id ? Slide::find($id) : null;
        if ($id && !$slide) {
            $this->flash('error', 'Слайд не найден.');
            $this->redirect('/slides');
        }
        $errors = [];
        $values = $slide ?? [
            'eyebrow' => '', 'title' => '', 'text' => '', 'btn1_text' => '', 'btn1_url' => '', 'btn2_text' => '', 'btn2_url' => '',
            'image' => '', 'image_alt' => '', 'caption' => '', 'sort' => 0, 'is_active' => 1,
        ];
        if ($this->isValidPost()) {
            $in = $_POST;
            $newFile = null;
            try {
                if ($file = Upload::fromRequest('image')) {
                    $newFile = Upload::storeImage($file, Slide::UPLOAD_DIR, Upload::SLIDE_IMAGES);
                    $in['image'] = $newFile;
                }
                $res = Slide::save($in, $id);
                if (!$res['errors']) {
                    // При замене фото старый файл удаляется с сервера.
                    if ($newFile && $slide && $slide['image']) {
                        Upload::delete(Slide::UPLOAD_DIR, $slide['image']);
                    }
                    $this->flash('success', 'Слайд сохранён.');
                    $this->redirect('/slides');
                }
                $errors = $res['errors'];
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
            if ($newFile) {
                Upload::delete(Slide::UPLOAD_DIR, $newFile);
            }
            $values = array_merge($values, $_POST);
            $values['is_active'] = !empty($_POST['is_active']);
        }
        $this->render('slide-form', ['values' => $values, 'errors' => $errors, 'id' => $id],
            $id ? 'Редактирование слайда' : 'Новый слайд', 'slides');
    }

    public function delete(): void
    {
        $id = Request::getInt('id');
        $slide = Slide::find($id);
        if (!$slide) {
            $this->flash('error', 'Слайд не найден.');
            $this->redirect('/slides');
        }
        if ($this->isValidPost()) {
            $err = Slide::delete($id);
            $this->flash($err ? 'error' : 'success', $err ?? 'Слайд удалён.');
            $this->redirect('/slides');
        }
        if ((int) $slide['is_active'] === 1 && Slide::activeCount() <= 1) {
            $this->flash('error', 'Нельзя удалить последний активный слайд: на сайте должен оставаться хотя бы один.');
            $this->redirect('/slides');
        }
        $this->confirm('Удаление слайда', 'Удалить слайд «' . $slide['title'] . '»? Файл фото тоже будет удалён с сервера.', '/slides', 'slides');
    }
}
