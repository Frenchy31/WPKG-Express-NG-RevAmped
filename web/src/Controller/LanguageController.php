<?php

declare(strict_types=1);

namespace App\Controller;

use App\View\Language;
use Cake\Event\EventInterface;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Cookie\CookieInterface;
use Cake\Http\Response;
use DateTimeImmutable;

/**
 * Stores the interface language chosen with the footer selector.
 */
class LanguageController extends AppController
{
    /**
     * Opens the selector to anonymous visitors, so that the login page honours the choice.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['update']);
    }

    /**
     * Sets the language cookie when the submitted value is known, then returns to the previous page.
     *
     * @return \Cake\Http\Response
     */
    public function update(): Response
    {
        $this->request->allowMethod(['post']);

        $language = Language::tryFrom((string)$this->request->getData('lang'));
        $response = $this->redirect($this->referer('/', true));
        if ($language === null) {
            return $response;
        }

        return $response->withCookie(Cookie::create(Language::COOKIE, $language->value, [
            'expires' => new DateTimeImmutable('+1 year'),
            'path' => '/',
            'httponly' => true,
            'samesite' => CookieInterface::SAMESITE_LAX,
        ]));
    }
}
