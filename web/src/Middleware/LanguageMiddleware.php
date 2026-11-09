<?php

declare(strict_types=1);

namespace App\Middleware;

use App\View\Language;
use Cake\Core\Configure;
use Cake\I18n\I18n;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Applies the language of the wpkg_lang cookie, or the server default, to every request.
 */
class LanguageMiddleware implements MiddlewareInterface
{
    /**
     * Sets the locale before the request is handled. Accept-Language is ignored on purpose.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Next handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = Language::fromCookie($request->getCookieParams()[Language::COOKIE] ?? null);
        I18n::setLocale($language?->locale() ?? (string)Configure::read('App.defaultLocale'));

        return $handler->handle($request);
    }
}
