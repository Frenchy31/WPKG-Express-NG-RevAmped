<?php

declare(strict_types=1);

namespace App\Controller;

use App\Feed\HostsFeed;
use App\Feed\PackagesFeed;
use App\Feed\ProfilesFeed;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

/**
 * Serves the XML documents read by wpkg.js.
 *
 * A database failure must surface as an HTTP error: an empty document would make clients
 * uninstall software.
 */
class FeedsController extends AppController
{
    /**
     * Applies the optional HTTP Basic protection of the feeds.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['hosts', 'profiles', 'packages', 'host', 'profile', 'package']);

        if (Configure::read('Wpkg.xml.protect') && !$this->isAllowedToRead()) {
            $event->setResult(
                $this->response
                    ->withStatus(401)
                    ->withHeader('WWW-Authenticate', 'Basic realm="wpkgExpress XML Export"'),
            );
        }
    }

    /**
     * Serves hosts.xml.
     *
     * @return \Cake\Http\Response
     */
    public function hosts(): Response
    {
        return $this->xml(HostsFeed::fromConfiguration()->document());
    }

    /**
     * Serves profiles.xml.
     *
     * @return \Cake\Http\Response
     */
    public function profiles(): Response
    {
        return $this->xml(ProfilesFeed::fromConfiguration()->document());
    }

    /**
     * Serves packages.xml.
     *
     * @return \Cake\Http\Response
     */
    public function packages(): Response
    {
        return $this->xml(PackagesFeed::fromConfiguration()->document());
    }

    /**
     * Serves the document of one host.
     *
     * @param int $id Host id.
     * @return \Cake\Http\Response
     */
    public function host(int $id): Response
    {
        return $this->xml(HostsFeed::fromConfiguration()->record($id));
    }

    /**
     * Serves the document of one profile.
     *
     * @param int $id Profile id.
     * @return \Cake\Http\Response
     */
    public function profile(int $id): Response
    {
        return $this->xml(ProfilesFeed::fromConfiguration()->record($id));
    }

    /**
     * Serves the document of one package.
     *
     * @param int $id Package id.
     * @return \Cake\Http\Response
     */
    public function package(int $id): Response
    {
        return $this->xml(PackagesFeed::fromConfiguration()->record($id));
    }

    /**
     * Builds the response of a document.
     *
     * @param string|null $document Document, null when the record is not exposed.
     * @return \Cake\Http\Response
     * @throws \Cake\Http\Exception\NotFoundException
     */
    private function xml(?string $document): Response
    {
        if ($document === null) {
            throw new NotFoundException();
        }

        return $this->response
            ->withHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->withStringBody($document);
    }

    /**
     * Tells whether the request may read a protected feed: logged-in administrator or Basic credentials.
     *
     * @return bool
     */
    private function isAllowedToRead(): bool
    {
        if ($this->Authentication->getIdentity() !== null) {
            return true;
        }

        $server = $this->request->getServerParams();
        $username = (string)($server['PHP_AUTH_USER'] ?? '');
        $password = (string)($server['PHP_AUTH_PW'] ?? '');
        $isExpectedUser = hash_equals((string)Configure::read('Wpkg.xml.user'), $username);
        $isExpectedPassword = password_verify($password, (string)Configure::read('Wpkg.xml.passwordHash'));

        return $isExpectedUser && $isExpectedPassword;
    }
}
