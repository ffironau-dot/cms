<?php
/**
 * Главная страница: слайдер, Title/Description и OG-теги из админки.
 */

declare(strict_types=1);

namespace Site;

use Core\Models\Slide;
use Core\Settings;
use Core\Upload;

final class HomeController extends Controller
{
    public function show(): void
    {
        $s = Settings::all();
        $seo = new Seo();
        $seo->title = $s['home_title'];
        $seo->description = $s['home_description'];
        $seo->canonical = '/';
        $seo->ogTitle = $s['home_og_title'] ?: $s['home_title'];
        $seo->ogDescription = $s['home_og_description'] ?: $s['home_description'];
        $seo->ogImage = Upload::url('og', $s['home_og_image']);

        $interval = max(1, (int) $s['slider_interval']);
        Layout::render('home', [
            'seo'             => $seo,
            'slides'          => Slide::active(),
            'sliderAutoplayMs' => $s['slider_autoplay'] === '1' ? $interval * 1000 : 0,
        ], '/');
    }
}
