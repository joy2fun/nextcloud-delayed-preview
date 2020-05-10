<?php

namespace OCA\DelayedPreview\Middleware;

use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;

class PreviewMiddleware extends Middleware {

    public function afterController($controller, $methodName, $response){
        if ($controller instanceof \OC\Core\Controller\PreviewController) {
            $root = \OC::$server->getRootFolder();
            $userId = \OC::$server->getUserSession()->getUser()->getUID();
            $request = \OC::$server->getRequest();
            $config = \OC::$server->getConfig();
            $userFolder = $root->getUserFolder($userId);

            if ($methodName === 'getPreview') {
                try {
                    $file = $userFolder->get($request->getParam('file'));
                } catch (NotFoundException $e) {
                    return new DataResponse([], Http::STATUS_NOT_FOUND);
                }
            } elseif ($methodName === 'getPreviewByFileId') {
                $nodes = $userFolder->getById($request->getParam('fileId'));

                if (\count($nodes) === 0) {
                    return new DataResponse([], Http::STATUS_NOT_FOUND);
                }
                $file = array_pop($nodes);
            }

            if ($response->getStatus() === Http::STATUS_NOT_FOUND) {
                $this->pushToQueue(
                    $userId,
                    $file->getId(),
                    $request->getParam('x'),
                    $request->getParam('y'),
                    !$request->getParam('a')
                );

                if ($config->getSystemValue('enable_waiting_previews', false)) {
                    if ($methodName === 'getPreviewByFileId') {
                        return $this->getWaitingPreviewResponse();
                    }
                }
            }
        }

        return $response;
    }

    protected function pushToQueue(
        $uid,
        $id,
        $width,
        $height,
        $crop
    ) {
        $redis = \OC::$server->getGetRedisFactory()->getInstance();
        $key = sprintf('dp:%s:%d:%d:%d', $id, $width, $height, $crop);
        if ($redis->setnx($key, 1)) {
            $redis->expire($key, 300);
            $redis->rPush('delayed_previews_queue', json_encode([
                'uid' => $uid,
                'id' => $id,
                'w' => $width,
                'h' => $height,
                'crop' => $crop,
            ], JSON_UNESCAPED_SLASHES));
        }
    }

    protected function getWaitingPreviewResponse() {
        $file = new \OCP\Files\SimpleFS\InMemoryFile(
            'waiting.png',
            file_get_contents(__DIR__ . '/../../img/waiting.png')
        );

        $response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
        $response->cacheFor(60);
        return $response;
    }
}
