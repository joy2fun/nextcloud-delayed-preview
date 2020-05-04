<?php

namespace OCA\DelayedPreview\AppInfo;

use OC\Server;
use OCA\DelayedPreview\Middleware\PreviewMiddleware;
use OCP\AppFramework\App;
use OCA\DelayedPreview\DelayedPreviewManager;
use OC\AppFramework\Middleware\MiddlewareDispatcher;

class Application extends \OC\Core\Application {
    public function __construct() {
        parent::__construct();

        $container = $this->getContainer();
        $server = $container->getServer();

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

        $container->registerService('PreviewMiddleware', function($c){
            return new PreviewMiddleware();
        });

        $container->registerMiddleware('PreviewMiddleware');
    }
}