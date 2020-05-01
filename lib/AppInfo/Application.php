<?php

namespace OCA\DelayedPreview\AppInfo;

use OC\Server;
use OCP\AppFramework\App;
use OCA\DelayedPreview\DelayedPreviewManager;
use OC\AppFramework\Middleware\MiddlewareDispatcher;

class Application extends App {
    public function __construct() {
        parent::__construct('delayedpreview');

        $server = $this->getContainer()->getServer();

        $server->registerService(\OCP\IPreview::class, function (Server $c) {
            return new DelayedPreviewManager(
                $c->getConfig(),
                $c->getRootFolder(),
                $c->getAppDataDir('preview'),
                $c->getEventDispatcher(),
                $c->getGeneratorHelper(),
                $c->getSession()->get('user_id')
            );
        });

        $server->registerAlias('PreviewManager', \OCP\IPreview::class);
    }
}