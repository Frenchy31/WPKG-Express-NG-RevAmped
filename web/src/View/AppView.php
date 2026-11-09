<?php

declare(strict_types=1);

namespace App\View;

use Cake\I18n\I18n;
use Cake\View\View;

/**
 * Application view.
 *
 * @property \App\View\Helper\WpkgHelper $Wpkg
 */
class AppView extends View
{
    /**
     * Loads helpers with the markup expected by plain.css.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->addHelper('Wpkg');
        $this->addHelper('Form', ['templates' => [
            'inputContainer' => '<div class="inputwrap">{{content}}</div>',
            'inputContainerError' => '<div class="inputwrap">{{content}}{{error}}</div>',
            'checkboxContainer' => '<div class="inputwrap">{{content}}</div>',
            'nestingLabel' => '{{hidden}}{{input}}<label{{attrs}}>{{text}}</label><br />',
        ]]);
        $this->addHelper('Paginator', ['templates' => [
            'nextActive' => '<a rel="next" href="{{url}}">{{text}}</a>',
            'nextDisabled' => '<span class="disabled">{{text}}</span>',
            'prevActive' => '<a rel="prev" href="{{url}}">{{text}}</a>',
            'prevDisabled' => '<span class="disabled">{{text}}</span>',
            'number' => '<a href="{{url}}">{{text}}</a> ',
            'current' => '<span class="current">{{text}}</span> ',
        ]]);
    }

    /**
     * Theme stored in the visitor's cookie.
     *
     * @return \App\View\Theme
     */
    public function theme(): Theme
    {
        return Theme::fromCookie($this->getRequest()->getCookie(Theme::COOKIE));
    }

    /**
     * Language of the displayed interface, English when the locale is not shipped.
     *
     * @return \App\View\Language
     */
    public function language(): Language
    {
        return Language::fromLocale(I18n::getLocale()) ?? Language::English;
    }
}
