<?php

namespace App\Controllers\Admin;

use App\Models\Option;
use Core\Http\Response;
use Core\Http\Request;
use Core\Http\Session;

class SettingController
{
    public function index(): Response
    {
        $settings = [
            'site_name'        => Option::get('site_name', config('app.name')),
            'site_description' => Option::get('site_description', ''),
            'site_keywords'    => Option::get('site_keywords', ''),
            'site_url'         => Option::get('site_url', config('app.url')),
            'posts_per_page'   => Option::get('posts_per_page', 10),
            'logo_url'         => Option::get('logo_url', ''),
            'footer_text'      => Option::get('footer_text', ''),
            'allow_registration' => Option::get('allow_registration', '0'),
            'moderate_comments'   => Option::get('moderate_comments', '1'),
            'baidu_analytics'    => Option::get('baidu_analytics', ''),
            'google_analytics'   => Option::get('google_analytics', ''),
        ];
        return view('admin.settings.index', [
            'settings'  => $settings,
            'pageTitle' => '站点设置',
        ]);
    }

    public function save(): Response
    {
        $request = app(Request::class);
        $sess = app(Session::class);
        $keys = [
            'site_name', 'site_description', 'site_keywords', 'site_url',
            'posts_per_page', 'logo_url', 'footer_text',
            'allow_registration', 'moderate_comments',
            'baidu_analytics', 'google_analytics',
        ];
        foreach ($keys as $k) {
            $v = $request->input($k, '');
            if (is_array($v)) $v = implode(',', $v);
            Option::set($k, (string) $v);
        }
        do_action('settings_saved', $request);
        $sess->flash('success', '设置已保存');
        return redirect(route('admin.settings.index'));
    }
}
